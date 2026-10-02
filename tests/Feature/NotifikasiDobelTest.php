<?php

namespace Tests\Feature;

use App\Models\Meeting;
use App\Models\NotificationLog;
use App\Models\Project;
use App\Models\User;
use App\Services\WhatsappNotifier;
use Carbon\Carbon;
use Database\Seeders\TestAccountSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * 🔁 REGRESI PESAN WA DOBEL (2 Okt 2026)
 *
 * Zev melaporkan banyak pesan WA yang duplikat. Setelah kode dibaca, ada 4 celah:
 *
 *  A. Anti-dobel hanya menghitung baris berstatus `sent`. Kalau WAHA LAMBAT lalu
 *     timeout, kita menulis `failed` — padahal WhatsApp bisa saja sudah menerima
 *     pesannya. Penjalan berikutnya melihat "belum pernah dikirim" -> kirim lagi.
 *  B. Anti-dobel dihitung per AKUN (user_id), bukan per NOMOR. Kalau dua akun
 *     memakai nomor WhatsApp yang sama, satu orang menerima pesan yang sama 2x.
 *  C. Untuk pesan tanpa project (rapat & action item), anti-dobel membandingkan
 *     teks PERSIS. Cukup satu huruf berubah (mis. lokasi rapat diedit) -> pesan
 *     yang sama dikirim lagi ke PIC yang sama. `notifyActionItems()` dipanggil
 *     dari 3 tempat (simpan, ubah, publikasi).
 *  D. Sejak notifikasi dikirim SETELAH respons, keputusan anti-dobel terjadi di
 *     fase pengiriman. Dua permintaan cepat (klik dobel / browser mengirim ulang)
 *     bisa sama-sama lolos sebelum salah satunya mencatat.
 */
class NotifikasiDobelTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(TestAccountSeeder::class);
        Carbon::setTestNow(Carbon::parse('2026-10-02 09:00:00'));
        config(['services.waha.enabled' => true]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    // ==========================================================
    // BANTUAN
    // ==========================================================

    private function pembina(): User
    {
        return User::where('division_role', 'pembina')->where('status', 'Aktif')->firstOrFail();
    }

    private function ketua(): User
    {
        return User::where('division_role', 'ketua_media')->where('status', 'Aktif')->firstOrFail();
    }

    private function anggota(string $nama, string $phone): User
    {
        return User::updateOrCreate(
            ['email' => 'dobel.' . strtolower(str_replace(' ', '', $nama)) . '@test.com'],
            [
                'name'          => $nama,
                'password'      => 'password',
                'role'          => 'Santri',
                'division'      => 'Tim Lapangan',
                'division_role' => 'anggota',
                'status'        => 'Aktif',
                'phone'         => $phone,
            ]
        );
    }

    /** Project di tahap Review, siap di-ACC dua pihak. */
    private function projectReview(array $anggota): Project
    {
        $project = Project::create([
            'name'       => 'Project Uji Dobel ' . now()->timestamp,
            'status'     => Project::STATUS_REVIEW,
            'start_date' => '2026-09-01',
            'deadline'   => '2026-12-31',
        ]);

        $project->users()->attach(array_map(fn ($u) => $u->id, $anggota));

        return $project;
    }

    private function rapat(string $judul, array $actionItems): Meeting
    {
        return Meeting::create([
            'title'        => $judul,
            'meeting_date' => '2026-10-02',
            'start_time'   => '08:00',
            'end_time'     => '09:00',
            'location'     => 'Aula',
            'attendees'    => [],
            'agenda'       => 'Pembahasan rutin',
            'action_items' => $actionItems,
            'created_by'   => $this->pembina()->id,
            'status'       => 'draft',
        ]);
    }

    private function jumlahPesan(string $type, string $phone): int
    {
        return NotificationLog::where('type', $type)->where('phone', $phone)->count();
    }

    private function fakeSukses(): void
    {
        Http::fake(['*' => Http::response(['id' => 'pesan-terkirim'], 200)]);
    }

    private function fakeGagal(): void
    {
        Http::fake(['*' => Http::response(['error' => 'WAHA sedang bermasalah'], 500)]);
    }

    // ==========================================================
    // D. KLIK DOBEL — cadangan pengiriman sebelum benar-benar kirim
    // ==========================================================

    public function test_cadangan_pengiriman_mencegah_klik_dobel(): void
    {
        $this->fakeSukses();

        $anggota  = $this->anggota('Anggota Klik Dobel', '628111111801');
        $notifier = app(WhatsappNotifier::class);

        $pertama = $notifier->siapkan($anggota, null, 'meeting_action_item', 'Pesan uji klik dobel', 'kunci-klik-dobel');
        $kedua   = $notifier->siapkan($anggota, null, 'meeting_action_item', 'Pesan uji klik dobel', 'kunci-klik-dobel');

        $this->assertNotNull($pertama, 'Permintaan pertama harus disiapkan');
        $this->assertNull($kedua, 'Permintaan kedua (klik dobel) harus ditolak karena sudah dicadangkan');

        // Hanya ada SATU baris tercatat, dan statusnya sudah 'pending' (cadangan) sebelum dikirim
        $this->assertSame(1, NotificationLog::where('dedupe_key', 'kunci-klik-dobel')->count());
        $this->assertSame('pending', NotificationLog::where('dedupe_key', 'kunci-klik-dobel')->value('status'));

        // Setelah dikirim, statusnya berubah jadi sent
        $notifier->kirim($pertama);
        $this->assertSame('sent', NotificationLog::where('dedupe_key', 'kunci-klik-dobel')->value('status'));
    }

    public function test_dua_request_cepat_hanya_mengirim_sekali(): void
    {
        $this->fakeSukses();

        $anggota = $this->anggota('Anggota Request Dobel', '628111111802');
        $project = $this->projectReview([$anggota]);

        $this->actingAs($this->pembina())->patch("/projects/{$project->id}/approve");
        $this->actingAs($this->ketua())->patch("/projects/{$project->id}/approve");
        // permintaan kedua (mis. browser mengirim ulang)
        $this->actingAs($this->ketua())->patch("/projects/{$project->id}/approve");

        $this->assertSame(1, $this->jumlahPesan('project_approved', '628111111802'),
            'Dua permintaan cepat tidak boleh mengirim pesan ACC dua kali');
    }

    /** Publikasikan ulang rapat (batalkan dulu ke draft, karena publish() menolak yang sudah published). */
    private function publishUlang(Meeting $rapat): void
    {
        $rapat->forceFill(['status' => 'draft'])->save();
        $this->actingAs($this->pembina())->patch("/meetings/{$rapat->id}/publish");
    }

    // ==========================================================
    // A. PERCOBAAN GAGAL — tahan 1 jam (mungkin sudah terkirim)
    // ==========================================================

    public function test_percobaan_gagal_tidak_diulang_dalam_satu_jam(): void
    {
        $anggota = $this->anggota('Anggota Gagal Ulang', '628111111803');
        $rapat   = $this->rapat('Rapat Uji Gagal', [
            ['assigned_to' => $anggota->id, 'task' => 'Buat notulen', 'deadline' => '5 Okt 2026'],
        ]);

        // Percobaan 1: WAHA bermasalah -> baris 'failed'
        $this->fakeGagal();
        $this->actingAs($this->pembina())->patch("/meetings/{$rapat->id}/publish");
        $this->assertSame(1, $this->jumlahPesan('meeting_action_item', '628111111803'), 'Percobaan 1 harus tercatat gagal');

        // 10 menit kemudian dicoba lagi (WAHA sudah normal)
        $this->fakeSukses();
        Carbon::setTestNow(Carbon::parse('2026-10-02 09:10:00'));
        $this->publishUlang($rapat->fresh());

        $this->assertSame(1, $this->jumlahPesan('meeting_action_item', '628111111803'),
            'Percobaan gagal harus ditahan dulu — bisa jadi pesannya SUDAH sampai (timeout ≠ tidak terkirim)');
    }

    public function test_percobaan_gagal_boleh_dicoba_lagi_keesokan_harinya(): void
    {
        $anggota = $this->anggota('Anggota Gagal Besok', '628111111804');
        $rapat   = $this->rapat('Rapat Uji Gagal Kedua', [
            ['assigned_to' => $anggota->id, 'task' => 'Buat notulen', 'deadline' => '5 Okt 2026'],
        ]);

        $this->fakeGagal();
        $this->actingAs($this->pembina())->patch("/meetings/{$rapat->id}/publish");

        // 2 jam kemudian: tahanan 1 jam sudah lewat -> boleh dicoba lagi
        $this->fakeSukses();
        Carbon::setTestNow(Carbon::parse('2026-10-02 11:00:00'));
        $this->publishUlang($rapat->fresh());

        $this->assertSame(2, $this->jumlahPesan('meeting_action_item', '628111111804'),
            'Setelah tahanan lewat, percobaan ulang harus diizinkan (kalau tidak, pesan hilang selamanya)');
    }

    // ==========================================================
    // B. SATU NOMOR DIPAKAI DUA AKUN
    // ==========================================================

    public function test_pesan_ke_nomor_yang_sama_tidak_dobel(): void
    {
        $this->fakeSukses();

        // Dua akun, satu nomor WhatsApp (mis. satu orang punya 2 akun)
        $satu = $this->anggota('Anggota Nomor Sama A', '628111111805');
        $dua  = $this->anggota('Anggota Nomor Sama B', '628111111805');

        $rapat = $this->rapat('Rapat Nomor Sama', []);
        $rapat->forceFill(['attendees' => [$satu->id, $dua->id]])->save();

        // Keduanya kepala divisi agar ikut penerima notulen
        $satu->forceFill(['division_role' => 'kepala_divisi'])->save();
        $dua->forceFill(['division_role' => 'kepala_divisi'])->save();

        $this->actingAs($this->pembina())->patch("/meetings/{$rapat->id}/publish");

        $this->assertSame(1, $this->jumlahPesan('meeting_published', '628111111805'),
            'Satu nomor WhatsApp tidak boleh menerima pesan yang sama dua kali walau ada dua akun');
    }

    // ==========================================================
    // C. RAPAT: simpan-ulang tidak boleh mengirim action item lagi
    // ==========================================================

    public function test_ubah_rapat_tidak_mengirim_action_item_lagi(): void
    {
        $anggota = $this->anggota('Anggota Action Item', '628111111806');

        $rapat = $this->rapat('Rapat Uji Action Item', [
            ['assigned_to' => $anggota->id, 'task' => 'Susun rundown', 'deadline' => '5 Okt 2026'],
        ]);

        $this->fakeSukses();
        $this->actingAs($this->pembina())->patch("/meetings/{$rapat->id}/publish");
        $this->assertSame(1, $this->jumlahPesan('meeting_action_item', '628111111806'));

        // Rapat diubah (JUDUL berubah — judul ini ikut masuk ke dalam isi pesan) lalu
        // dipublikasikan lagi. Tugasnya sendiri tidak berubah.
        $rapat->forceFill([
            'title'    => 'Rapat Uji Action Item (judul diperbarui)',
            'location' => 'Ruang Media',
            'agenda'   => 'Agenda diperbarui',
        ])->save();
        $this->publishUlang($rapat->fresh());

        $this->assertSame(1, $this->jumlahPesan('meeting_action_item', '628111111806'),
            'Tugas yang SAMA tidak boleh dikirim ulang hanya karena judul rapat berubah');
    }

    public function test_publikasi_ulang_notulen_dalam_30_menit_tidak_dobel(): void
    {
        $this->fakeSukses();

        $rapat = $this->rapat('Rapat Publikasi Ulang', []);
        $ketua = User::where('division_role', 'ketua_media')->where('status', 'Aktif')->firstOrFail();

        $this->actingAs($this->pembina())->patch("/meetings/{$rapat->id}/publish");
        $this->publishUlang($rapat->fresh());

        $this->assertSame(1, NotificationLog::where('type', 'meeting_published')->where('phone', $ketua->phone)->count(),
            'Publikasi berulang dalam 30 menit tidak boleh mengirim notulen yang sama dua kali');
    }

    public function test_action_item_berbeda_ke_orang_yang_sama_tetap_terkirim(): void
    {
        $anggota = $this->anggota('Anggota Dua Tugas', '628111111807');

        $rapat = $this->rapat('Rapat Dua Tugas', [
            ['assigned_to' => $anggota->id, 'task' => 'Tugas pertama', 'deadline' => '5 Okt 2026'],
            ['assigned_to' => $anggota->id, 'task' => 'Tugas kedua', 'deadline' => '6 Okt 2026'],
        ]);

        $this->fakeSukses();
        $this->actingAs($this->pembina())->patch("/meetings/{$rapat->id}/publish");

        $this->assertSame(2, $this->jumlahPesan('meeting_action_item', '628111111807'),
            'Dua tugas berbeda tetap harus dikirim dua kali (jangan sampai anti-dobel berlebihan)');
    }

    // ==========================================================
    // PESAN ACC: sekali per project, tapi tetap dikabari lagi setelah dibuka ulang
    // ==========================================================

    public function test_pesan_acc_tidak_dobel_dalam_satu_putaran_tapi_bisa_lagi_setelah_reopen(): void
    {
        $this->fakeSukses();

        $anggota = $this->anggota('Anggota ACC', '628111111808');
        $project = $this->projectReview([$anggota]);

        // Putaran 1: kedua pihak ACC -> 1 pesan
        $this->actingAs($this->pembina())->patch("/projects/{$project->id}/approve");
        $this->actingAs($this->ketua())->patch("/projects/{$project->id}/approve");
        $this->assertSame(1, $this->jumlahPesan('project_approved', '628111111808'), 'Putaran 1 harus 1 pesan');

        $idPutaran1 = NotificationLog::where('type', 'project_approved')
            ->where('phone', '628111111808')->value('id');

        // Project ditandai selesai (wajib punya minimal 1 konten), lalu dibuka kembali ke Review
        DB::table('contents')->insert([
            'content_code' => 'UJI-ACC-1',
            'project_id'   => $project->id,
            'title'        => 'Konten uji ACC',
            'platform'     => 'Instagram',
            'upload_type'  => 'activity',
            'status'       => 'Ready',
            'created_at'   => now(),
            'updated_at'   => now(),
        ]);

        $this->actingAs($this->ketua())->patch("/projects/{$project->id}/mark-complete");
        $this->assertSame(Project::STATUS_COMPLETED, $project->fresh()->status, 'Project harus masuk Completed dulu');

        $this->actingAs($this->pembina())->patch("/projects/{$project->id}/reopen", [
            'target_status' => Project::STATUS_REVIEW,
            'note'          => 'Ada bagian yang perlu diperbaiki dulu.',
        ]);
        $this->assertSame(Project::STATUS_REVIEW, $project->fresh()->status, 'Project kembali ke tahap Review');

        // Reopen harus MENGHAPUS catatan ACC lama, kalau tidak pesan ACC berikutnya
        // akan dibatalkan diam-diam oleh anti-dobel.
        $this->assertSame(0, $this->jumlahPesan('project_approved', '628111111808'),
            'Catatan ACC lama harus direset saat project dibuka ulang');

        // Putaran 2: kedua pihak ACC lagi -> pesan ACC harus terkirim lagi (baris BARU)
        $this->actingAs($this->pembina())->patch("/projects/{$project->id}/approve");
        $this->actingAs($this->ketua())->patch("/projects/{$project->id}/approve");

        $this->assertSame(1, $this->jumlahPesan('project_approved', '628111111808'),
            'Setelah dibuka ulang, ACC berikutnya harus tetap dikabari');

        $this->assertNotSame(
            $idPutaran1,
            NotificationLog::where('type', 'project_approved')->where('phone', '628111111808')->value('id'),
            'Pesan ACC putaran 2 harus BARU — bukti benar-benar dikirim ulang, bukan sisa putaran 1'
        );
    }
}
