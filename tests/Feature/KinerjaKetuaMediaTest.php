<?php

namespace Tests\Feature;

use App\Models\KetuaMediaEvaluation;
use App\Models\Meeting;
use App\Models\Project;
use App\Models\ProjectTask;
use App\Models\User;
use App\Services\KetuaMediaPerformance;
use Carbon\Carbon;
use Database\Seeders\TestAccountSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * ⚡ FITUR BARU: menu "Kinerja Ketua Media" (khusus Pembina).
 *
 * Yang dikunci tes ini:
 *  - hak akses (hanya Pembina, penjagaan 2 lapis: route + controller)
 *  - 4 pilar & bobot benar-benar ada dan angkanya masuk akal
 *  - task lewat tanggal / project lewat deadline MENURUNKAN skor divisi
 *  - pilar tanpa data TIDAK dianggap 0/100, tapi dikeluarkan & bobot dibagikan
 *  - bulan tanpa data di tren = kosong (bukan 0)
 *  - penanda waktu kecepatan alur terisi saat aksi workflow dijalankan
 *  - bottleneck menangkap project yang menunggu Ketua Media
 *  - catatan evaluasi: snapshot skor, tanggal tersimpan 'Y-m-d', hak akses
 */
class KinerjaKetuaMediaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Http::fake();   // jangan kirim notifikasi WA sungguhan
        $this->seed(TestAccountSeeder::class);
    }

    // ==========================================================
    // HELPER
    // ==========================================================

    private function pembina(): User
    {
        return User::where('division_role', 'pembina')->where('status', 'Aktif')->firstOrFail();
    }

    private function ketua(): User
    {
        return User::where('division_role', 'ketua_media')->where('status', 'Aktif')->firstOrFail();
    }

    private function kepalaDivisi(string $divisi): User
    {
        return User::where('division', $divisi)
            ->where('division_role', 'kepala_divisi')
            ->where('status', 'Aktif')
            ->firstOrFail();
    }

    private function anggotaDivisi(string $divisi): User
    {
        return User::where('division', $divisi)
            ->where('division_role', 'anggota')
            ->where('status', 'Aktif')
            ->firstOrFail();
    }

    private function layanan(): KetuaMediaPerformance
    {
        return app(KetuaMediaPerformance::class);
    }

    private function buatProject(string $status, array $anggotaIds, ?string $deadline = null, array $tambahan = []): Project
    {
        $project = Project::create(array_merge([
            'name'       => 'Project Uji ' . uniqid(),
            'start_date' => Carbon::today()->toDateString(),
            'deadline'   => $deadline ?? Carbon::today()->addDays(5)->toDateString(),
            'status'     => $status,
        ], $tambahan));

        $project->users()->attach($anggotaIds);

        return $project;
    }

    private function buatTask(
        Project $project,
        int $assignedTo,
        string $status,
        ?string $taskDate = null,
        int $weight = 5,
        ?string $completedAt = null
    ): ProjectTask {
        $task = ProjectTask::create([
            'project_id'   => $project->id,
            'assigned_to'  => $assignedTo,
            'title'        => 'Task Uji ' . uniqid(),
            'task_date'    => $taskDate ?? Carbon::today()->toDateString(),
            'status'       => $status,
            'weight'       => $weight,
            'is_approved'  => true,
            'scored_at'    => now(),
            'completed_at' => $completedAt,
        ]);

        $task->contributors()->attach([$assignedTo]);

        return $task;
    }

    /** Baris divisi dari hasil evaluasi. */
    private function barisDivisi(array $hasil, string $nama): ?array
    {
        foreach ($hasil['divisi'] as $d) {
            if ($d['nama'] === $nama) {
                return $d;
            }
        }

        return null;
    }

    private function evaluasiSekarang(array $data = null): array
    {
        return $this->layanan()->evaluate(
            $this->ketua(),
            now()->subDays(29)->startOfDay(),
            now()->endOfDay(),
            $data
        );
    }

    // ==========================================================
    // HAK AKSES
    // ==========================================================

    public function test_pembina_bisa_membuka_halaman_kinerja_ketua_media(): void
    {
        $res = $this->actingAs($this->pembina())->get('/kinerja-ketua-media');

        $res->assertOk();
        $res->assertSee('Kinerja Ketua Media');
        $res->assertSee($this->ketua()->name);
        $res->assertSee('Kesehatan Divisi');
        $res->assertSee('Kecepatan Alur');
        $res->assertSee('Keterlibatan');
        $res->assertSee('Ketuntasan');
    }

    public function test_ketua_media_tidak_bisa_membuka(): void
    {
        $this->actingAs($this->ketua())->get('/kinerja-ketua-media')->assertForbidden();
    }

    public function test_kepala_divisi_tidak_bisa_membuka(): void
    {
        $this->actingAs($this->kepalaDivisi('Lapangan'))
            ->get('/kinerja-ketua-media')
            ->assertForbidden();
    }

    public function test_anggota_tidak_bisa_membuka(): void
    {
        $this->actingAs($this->anggotaDivisi('Editing'))
            ->get('/kinerja-ketua-media')
            ->assertForbidden();
    }

    public function test_tamu_diarahkan_ke_halaman_login(): void
    {
        $this->get('/kinerja-ketua-media')->assertRedirect('/login');
    }

    public function test_menu_sidebar_hanya_muncul_untuk_pembina(): void
    {
        $this->actingAs($this->pembina())->get('/dashboard')
            ->assertOk()
            ->assertSee('Kinerja Ketua Media');

        $this->actingAs($this->ketua())->get('/dashboard')
            ->assertOk()
            ->assertDontSee('Kinerja Ketua Media');
    }

    // ==========================================================
    // STRUKTUR HASIL & PAPAN DIVISI
    // ==========================================================

    public function test_empat_pilar_dan_divisi_ikut_terhitung(): void
    {
        $hasil = $this->evaluasiSekarang();

        $this->assertCount(4, $hasil['pilar']);
        foreach (['kesehatan_divisi', 'kecepatan_alur', 'keterlibatan', 'ketuntasan'] as $kunci) {
            $this->assertArrayHasKey($kunci, $hasil['pilar']);
        }

        // 4 divisi operasional dari seeder (Lapangan, Editing, Broadcast, AI Research)
        $this->assertCount(4, $hasil['divisi']);

        $namaDivisi = array_column($hasil['divisi'], 'nama');
        sort($namaDivisi);
        $this->assertSame(['AI Research', 'Broadcast', 'Editing', 'Lapangan'], $namaDivisi);
    }

    public function test_bobot_pilar_total_100_persen(): void
    {
        $this->assertSame(1.0, round(array_sum(KetuaMediaPerformance::BOBOT), 2));
    }

    // ==========================================================
    // KESEHATAN DIVISI — TASK LEWAT TANGGAL
    // ==========================================================

    public function test_task_lewat_tanggal_menurunkan_skor_divisi(): void
    {
        $kepala  = $this->kepalaDivisi('Lapangan');
        $anggota = $this->anggotaDivisi('Lapangan');

        $project = $this->buatProject(Project::STATUS_ON_PROGRESS, [$kepala->id, $anggota->id]);
        $task = $this->buatTask($project, $anggota->id, 'Pending', Carbon::today()->toDateString());

        // ── Kondisi bersih: task berjadwal hari ini, belum telat ──
        $bersih = $this->evaluasiSekarang($this->layanan()->dataset());
        $this->assertSame(100, $this->barisDivisi($bersih, 'Lapangan')['skor']);

        // ── Sekarang dibuat telat: jadwal 5 hari lalu, belum selesai ──
        $task->update(['task_date' => Carbon::today()->subDays(5)->toDateString()]);

        $kotor = $this->evaluasiSekarang($this->layanan()->dataset());
        $skorKotor = $this->barisDivisi($kotor, 'Lapangan')['skor'];

        $this->assertLessThan(100, $skorKotor);
        $this->assertSame(1, $this->barisDivisi($kotor, 'Lapangan')['lewat']);
    }

    public function test_project_lewat_deadline_menurunkan_skor_divisi(): void
    {
        $kepala  = $this->kepalaDivisi('Editing');
        $anggota = $this->anggotaDivisi('Editing');

        // Deadline di dalam periode 30 hari & sudah lewat, project belum selesai
        $this->buatProject(Project::STATUS_ON_PROGRESS, [$kepala->id, $anggota->id], Carbon::today()->subDays(3)->toDateString());

        $hasil = $this->evaluasiSekarang($this->layanan()->dataset());
        $baris = $this->barisDivisi($hasil, 'Editing');

        $this->assertSame(1, $baris['project_jatuh_tempo']);
        $this->assertSame(1, $baris['project_telat_periode']);
        $this->assertLessThan(100, $baris['skor']);
    }

    // ==========================================================
    // PILAR TANPA DATA
    // ==========================================================

    public function test_pilar_tanpa_data_dikeluarkan_dan_bobot_dibagikan(): void
    {
        $anggota = $this->anggotaDivisi('Broadcast');
        $project = $this->buatProject(
            Project::STATUS_ON_PROGRESS,
            [$anggota->id],
            Carbon::today()->addDays(10)->toDateString()
        );

        // Satu task selesai TEPAT WAKTU di bulan lalu (bukan bulan ini):
        // → pilar kesehatan ada datanya, tapi pilar kecepatan/ketuntasan tidak
        $tanggalLalu = now()->subMonth()->startOfMonth()->addDays(5);
        $task = $this->buatTask(
            $project,
            $anggota->id,
            'Completed',
            $tanggalLalu->toDateString(),
            5,
            $tanggalLalu->copy()->setTime(10, 0)->toDateTimeString()
        );

        $hasil = $this->layanan()->evaluate(
            $this->ketua(),
            $tanggalLalu->copy()->startOfMonth(),
            $tanggalLalu->copy()->endOfMonth()
        );

        // Kecepatan alur tidak punya data di bulan lampau → null, bukan 0
        $this->assertFalse($hasil['pilar']['kecepatan_alur']['tersedia']);
        $this->assertNull($hasil['pilar']['kecepatan_alur']['skor']);

        // Kesehatan divisi punya data (task tepat waktu) → 100
        $this->assertSame(100, $hasil['pilar']['kesehatan_divisi']['skor']);

        // Keterlibatan: tidak ada kegiatan Ketua Media → 0 (memang 0, bukan null)
        $this->assertSame(0, $hasil['pilar']['keterlibatan']['skor']);

        // Skor total = hanya dari pilar yang ada datanya (bobot dibagikan):
        // (100 × 0.40 + 0 × 0.20) / (0.40 + 0.20) = 67
        $this->assertSame(67, $hasil['skor']);

        $this->assertNotContains('Kecepatan Alur', $hasil['pilar_dinilai']);
        $this->assertContains('Kesehatan Divisi', $hasil['pilar_dinilai']);
    }

    public function test_periode_tanpa_jejak_data_menghasilkan_skor_kosong(): void
    {
        $hasil = $this->layanan()->evaluate(
            $this->ketua(),
            now()->subMonths(10)->startOfMonth(),
            now()->subMonths(10)->endOfMonth()
        );

        $this->assertNull($hasil['skor']);
        $this->assertSame('Data belum cukup', $hasil['label']);
        $this->assertSame([], $hasil['pilar_dinilai']);
    }

    public function test_tren_bulan_tanpa_data_kosong_bukan_nol(): void
    {
        $tren = $this->layanan()->trend($this->ketua(), 6);

        $this->assertCount(6, $tren['label']);
        $this->assertCount(6, $tren['data']);

        // Bulan paling lampau (6 bulan lalu) belum ada data sama sekali
        $this->assertNull($tren['data'][0]);

        // Selalu stabil kalau dihitung dua kali (tidak ikut berubah)
        $this->assertSame($tren['data'], $this->layanan()->trend($this->ketua(), 6)['data']);
    }

    // ==========================================================
    // KECEPATAN ALUR — PENANDA WAKTU
    // ==========================================================

    public function test_submit_task_review_mengisi_penanda_waktu_dan_pelaku(): void
    {
        $ketua  = $this->ketua();
        $anggota = $this->anggotaDivisi('AI Research');

        $project = $this->buatProject(Project::STATUS_PLANNING, [$anggota->id]);
        $this->buatTask($project, $anggota->id, 'Pending');

        $this->assertNull($project->fresh()->task_submitted_at);

        $this->actingAs($ketua)->patch("/projects/{$project->id}/submit-task-review");

        $project->refresh();
        $this->assertSame(Project::STATUS_TASK_REVIEW, $project->status);
        $this->assertNotNull($project->task_submitted_at);
        $this->assertSame($ketua->id, (int) $project->task_submitted_by);
    }

    public function test_request_review_mengisi_penanda_masuk_tahap_review(): void
    {
        $kepala  = $this->kepalaDivisi('Editing');
        $project = $this->buatProject(Project::STATUS_ON_PROGRESS, [$kepala->id]);

        $this->assertNull($project->fresh()->review_requested_at);

        $this->actingAs($kepala)->patch("/projects/{$project->id}/request-review", [
            'note' => 'Semua task sudah selesai, mohon diperiksa.',
        ]);

        $project->refresh();
        $this->assertSame(Project::STATUS_REVIEW, $project->status);
        $this->assertNotNull($project->review_requested_at);
    }

    // ==========================================================
    // BOTTLENECK
    // ==========================================================

    public function test_project_planning_tanpa_task_muncul_di_bottleneck(): void
    {
        $anggota = $this->anggotaDivisi('Lapangan');
        $project = $this->buatProject(Project::STATUS_PLANNING, [$anggota->id]);

        $hasil = $this->evaluasiSekarang($this->layanan()->dataset());

        $ketemu = collect($hasil['bottleneck'])->firstWhere('project_id', $project->id);

        $this->assertNotNull($ketemu, 'Project Planning tanpa task harus muncul di bottleneck');
        $this->assertSame('planning', $ketemu['jenis']);
        $this->assertSame('Task belum disusun', $ketemu['label']);
    }

    public function test_project_menunggu_acc_muncul_lalu_hilang_setelah_di_acc(): void
    {
        $ketua   = $this->ketua();
        $pembina = $this->pembina();
        $kepala  = $this->kepalaDivisi('Broadcast');

        $project = $this->buatProject(Project::STATUS_ON_PROGRESS, [$kepala->id]);

        // Kepala divisi ajukan review → project masuk Review, belum di-ACC
        $this->actingAs($kepala)->patch("/projects/{$project->id}/request-review", [
            'note' => 'Mohon diperiksa hasil kerjanya.',
        ]);

        $hasil = $this->evaluasiSekarang($this->layanan()->dataset());
        $ketemu = collect($hasil['bottleneck'])->firstWhere('project_id', $project->id);

        $this->assertNotNull($ketemu, 'Project yang menunggu ACC harus muncul di bottleneck');
        $this->assertSame('acc', $ketemu['jenis']);

        // Pembina & Ketua Media ACC
        $this->actingAs($pembina)->patch("/projects/{$project->id}/approve");
        $this->actingAs($ketua)->patch("/projects/{$project->id}/approve");

        $sesudah = $this->evaluasiSekarang($this->layanan()->dataset());

        $this->assertNull(
            collect($sesudah['bottleneck'])->firstWhere('project_id', $project->id),
            'Setelah di-ACC, project tidak boleh lagi muncul sebagai bottleneck'
        );
        $this->assertNotNull($project->fresh()->approved_by_ketua_at);
    }

    // ==========================================================
    // KETERLIBATAN
    // ==========================================================

    public function test_rapat_buatan_ketua_menaikkan_skor_keterlibatan(): void
    {
        $ketua = $this->ketua();

        $sebelum = $this->evaluasiSekarang($this->layanan()->dataset())['pilar']['keterlibatan']['skor'];

        for ($i = 0; $i < 4; $i++) {
            Meeting::create([
                'title'        => 'Rapat Uji ' . $i,
                'meeting_date' => Carbon::today()->toDateString(),
                'start_time'   => '08:00',
                'end_time'     => '09:00',
                'location'     => 'Aula',
                'status'       => 'published',
                'created_by'   => $ketua->id,
            ]);
        }

        $sesudah = $this->evaluasiSekarang($this->layanan()->dataset())['pilar']['keterlibatan']['skor'];

        $this->assertGreaterThan($sebelum, $sesudah);
    }

    public function test_hari_aktif_login_masuk_penilaian_keterlibatan(): void
    {
        $ketua = $this->ketua();

        $sebelum = $this->evaluasiSekarang($this->layanan()->dataset())['pilar']['keterlibatan']['skor'];

        foreach ([3, 2, 1] as $mundur) {
            DB::table('login_rewards')->insert([
                'user_id'     => $ketua->id,
                'reward_date' => Carbon::today()->subDays($mundur)->toDateString(),
                'points'      => 2,
                'streak'      => 1,
                'created_at'  => now(),
                'updated_at'  => now(),
            ]);
        }

        $sesudah = $this->evaluasiSekarang($this->layanan()->dataset())['pilar']['keterlibatan']['skor'];

        $this->assertGreaterThan($sebelum, $sesudah);
    }

    // ==========================================================
    // TIMELINE
    // ==========================================================

    public function test_jejak_kepemimpinan_memuat_acc_dan_rapat(): void
    {
        $ketua   = $this->ketua();
        $pembina = $this->pembina();
        $kepala  = $this->kepalaDivisi('Editing');

        $project = $this->buatProject(Project::STATUS_ON_PROGRESS, [$kepala->id]);
        $this->actingAs($kepala)->patch("/projects/{$project->id}/request-review", [
            'note' => 'Mohon diperiksa hasil kerjanya.',
        ]);
        $this->actingAs($pembina)->patch("/projects/{$project->id}/approve");
        $this->actingAs($ketua)->patch("/projects/{$project->id}/approve");

        Meeting::create([
            'title'        => 'Rapat Evaluasi Mingguan',
            'meeting_date' => Carbon::today()->toDateString(),
            'start_time'   => '08:00',
            'end_time'     => '09:00',
            'location'     => 'Aula',
            'status'       => 'published',
            'created_by'   => $ketua->id,
        ]);

        $timeline = $this->evaluasiSekarang($this->layanan()->dataset())['timeline'];
        $judul = array_column($timeline, 'judul');

        $this->assertContains('Memberi ACC project', $judul);
        $this->assertContains('Membuat rapat/notulen', $judul);
    }

    // ==========================================================
    // CATATAN EVALUASI
    // ==========================================================

    public function test_pembina_bisa_menyimpan_catatan_evaluasi_dengan_snapshot_skor(): void
    {
        $pembina = $this->pembina();
        $ketua   = $this->ketua();

        $skorSekarang = $this->evaluasiSekarang()['skor'];

        $res = $this->actingAs($pembina)->post('/kinerja-ketua-media/catatan', [
            'user_id'      => $ketua->id,
            'note'         => 'Broadcast masih ada task lewat tanggal, tolong dikejar minggu ini.',
            'period_start' => Carbon::today()->subDays(29)->toDateString(),
            'period_end'   => Carbon::today()->toDateString(),
            'days'         => 30,
        ]);

        $res->assertRedirect();

        $row = KetuaMediaEvaluation::first();
        $this->assertNotNull($row);
        $this->assertSame($ketua->id, (int) $row->user_id);
        $this->assertSame($pembina->id, (int) $row->evaluator_id);
        $this->assertSame($skorSekarang, $row->score);
        $this->assertNotNull($row->rating);

        // ⚡ REGRESI: tanggal WAJIB tersimpan 'Y-m-d' (bug kelas DateOnly).
        // Kalau ter-cast 'date', nilainya jadi 'Y-m-d 00:00:00' dan filter periode meleset.
        $mentah = DB::table('ketua_media_evaluations')->first();
        $this->assertSame(Carbon::today()->subDays(29)->toDateString(), $mentah->period_start);
        $this->assertSame(Carbon::today()->toDateString(), $mentah->period_end);
    }

    public function test_catatan_evaluasi_hanya_untuk_ketua_media(): void
    {
        $res = $this->actingAs($this->pembina())->post('/kinerja-ketua-media/catatan', [
            'user_id' => $this->anggotaDivisi('Lapangan')->id,
            'note'    => 'Catatan yang seharusnya ditolak karena bukan Ketua Media.',
            'days'    => 30,
        ]);

        $res->assertSessionHas('error');
        $this->assertSame(0, KetuaMediaEvaluation::count());
    }

    public function test_catatan_kurang_10_karakter_ditolak(): void
    {
        $res = $this->actingAs($this->pembina())->post('/kinerja-ketua-media/catatan', [
            'user_id' => $this->ketua()->id,
            'note'    => 'pendek',
            'days'    => 30,
        ]);

        $res->assertSessionHasErrors('note');
        $this->assertSame(0, KetuaMediaEvaluation::count());
    }

    public function test_hanya_pembina_yang_bisa_menghapus_catatan(): void
    {
        $pembina = $this->pembina();
        $ketua   = $this->ketua();

        $this->actingAs($pembina)->post('/kinerja-ketua-media/catatan', [
            'user_id' => $ketua->id,
            'note'    => 'Catatan evaluasi untuk diuji penghapusannya.',
            'days'    => 30,
        ]);

        $row = KetuaMediaEvaluation::firstOrFail();

        // Ketua Media tidak boleh menghapus
        $this->actingAs($ketua)
            ->delete("/kinerja-ketua-media/catatan/{$row->id}")
            ->assertForbidden();

        $this->assertSame(1, KetuaMediaEvaluation::count());

        // Pembina boleh
        $this->actingAs($pembina)
            ->delete("/kinerja-ketua-media/catatan/{$row->id}")
            ->assertRedirect();

        $this->assertSame(0, KetuaMediaEvaluation::count());
    }

    // ==========================================================
    // FILTER PERIODE
    // ==========================================================

    public function test_filter_days_tidak_valid_jatuh_ke_30_hari(): void
    {
        $res = $this->actingAs($this->pembina())->get('/kinerja-ketua-media?days=45');

        $res->assertOk();
        $this->assertSame(30, $res->viewData('filterDays'));
    }

    public function test_filter_7_dan_90_hari_diterima(): void
    {
        foreach ([7, 90] as $hari) {
            $res = $this->actingAs($this->pembina())->get('/kinerja-ketua-media?days=' . $hari);

            $res->assertOk();
            $this->assertSame($hari, $res->viewData('filterDays'));
        }
    }

    // ==========================================================
    // HALAMAN: KEADAAN TANPA KETUA MEDIA
    // ==========================================================

    public function test_halaman_tetap_aman_kalau_belum_ada_ketua_media(): void
    {
        User::where('division_role', 'ketua_media')->update(['division_role' => 'anggota']);

        $res = $this->actingAs($this->pembina())->get('/kinerja-ketua-media');

        $res->assertOk();
        $res->assertSee('Belum ada user dengan peran');
        $this->assertNull($res->viewData('ketua'));
    }

    // ==========================================================
    // REGRESI: ANGKA "MENUNGGU TINDAKAN" HARUS SAMA DENGAN DAFTARNYA
    // (bug 29 Sep: kartu pilar menulis "0 project" padahal daftar bottleneck
    //  berisi banyak item — karena dihitung dua tempat dengan syarat berbeda)
    // ==========================================================

    public function test_angka_menunggu_tindakan_sama_dengan_jumlah_baris_daftar(): void
    {
        $kepala = $this->kepalaDivisi('Lapangan');

        // Project sudah masuk Review SEBELUM kolom penanda waktu ada
        // → review_requested_at NULL (persis kondisi data lama di server)
        $this->buatProject(Project::STATUS_REVIEW, [$kepala->id]);

        $hasil  = $this->evaluasiSekarang($this->layanan()->dataset());
        $daftar = $hasil['bottleneck'];
        $sub    = $hasil['pilar']['kecepatan_alur']['detail']['sub']['menunggu_tindakan'] ?? null;

        $this->assertCount(1, $daftar, 'Project yang menunggu ACC harus ada di daftar');
        $this->assertNotNull($sub, 'Sub-metrik "menunggu tindakan" harus ada');
        $this->assertSame(
            count($daftar),
            (int) $sub['nilai'],
            'Angka di kartu pilar HARUS sama dengan jumlah baris daftar bottleneck'
        );
    }

    public function test_beberapa_revisi_pada_project_sama_dikelompokkan_jadi_satu_item(): void
    {
        $kepala  = $this->kepalaDivisi('Editing');
        $project = $this->buatProject(Project::STATUS_REVISION, [$kepala->id]);

        foreach (['Perbaiki transisi', 'Perbaiki audio', 'Perbaiki warna'] as $catatan) {
            \App\Models\ProjectRevision::create([
                'project_id' => $project->id,
                'user_id'    => $kepala->id,
                'type'       => 'revision',
                'note'       => $catatan,
            ]);
        }

        $hasil = $this->evaluasiSekarang($this->layanan()->dataset());

        $itemRevisi = collect($hasil['bottleneck'])
            ->where('jenis', 'revisi')
            ->where('project_id', $project->id);

        $this->assertCount(1, $itemRevisi, 'Revisi pada project yang sama harus jadi SATU baris, bukan diulang');
        $this->assertStringContainsString('3', $itemRevisi->first()['label']);
    }

    public function test_project_review_tanpa_penanda_waktu_pakai_perkiraan_yang_ditandai_jujur(): void
    {
        $kepala  = $this->kepalaDivisi('Broadcast');
        $project = $this->buatProject(Project::STATUS_REVIEW, [$kepala->id]);

        // Data lama: tidak ada penanda waktu masuk Review
        $project->forceFill(['review_requested_at' => null, 'updated_at' => Carbon::now()->subDays(4)])->save();

        $hasil = $this->evaluasiSekarang($this->layanan()->dataset());
        $item  = collect($hasil['bottleneck'])->firstWhere('project_id', $project->id);

        $this->assertNotNull($item);
        $this->assertNotNull($item['hari'], 'Lama menunggu tidak boleh kosong kalau bisa diperkirakan');
        $this->assertTrue($item['perkiraan'] ?? false, 'Nilai perkiraan WAJIB ditandai supaya tidak dikira angka pasti');
    }

    public function test_project_tanpa_anggota_dilaporkan_bukan_dihapus_diam_diam(): void
    {
        // Project tanpa anggota tidak bisa dipetakan ke divisi mana pun.
        // Kalau dibiarkan diam-diam, Pembina tidak tahu ada project "nyasar".
        Project::create([
            'name'       => 'Project Tanpa Anggota ' . uniqid(),
            'start_date' => Carbon::today()->toDateString(),
            'deadline'   => Carbon::today()->addDays(3)->toDateString(),
            'status'     => Project::STATUS_ON_PROGRESS,
        ]);

        $hasil = $this->evaluasiSekarang($this->layanan()->dataset());

        $this->assertGreaterThanOrEqual(
            1,
            $hasil['ringkasan']['project_tanpa_anggota'] ?? 0,
            'Project tanpa anggota harus dihitung & dilaporkan'
        );
    }

    public function test_tampilan_target_tidak_dobel(): void
    {
        // Perlu ada data di periode supaya kartu pilar menampilkan rincian sub-metrik
        $kepala  = $this->kepalaDivisi('Lapangan');
        $anggota = $this->anggotaDivisi('Lapangan');
        $project = $this->buatProject(Project::STATUS_ON_PROGRESS, [$kepala->id, $anggota->id]);
        $this->buatTask($project, $anggota->id, 'Pending', Carbon::today()->toDateString());

        $res = $this->actingAs($this->pembina())->get('/kinerja-ketua-media');

        $res->assertOk();
        $res->assertSee('target:');
        $this->assertStringNotContainsString(
            '0 item / 0 item',
            $res->getContent(),
            'Tampilan target tidak boleh dobel (nilai / target yang sama)'
        );
    }
}
