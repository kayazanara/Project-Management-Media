<?php

namespace Tests\Feature;

use App\Models\NotificationLog;
use App\Models\Project;
use App\Models\ProjectRevision;
use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\TestAccountSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * ⚡ FITUR BARU: "Setujui & Lanjut Sekarang" — Pembina bisa melanjutkan project dari
 * tahap Review TANPA menunggu ACC Ketua Media.
 *
 * Yang dikunci tes ini:
 *  - hanya Pembina, hanya saat status Review, alasan wajib min 10 karakter
 *  - status berpindah Review -> Upload
 *  - `approved_by_ketua_at` TETAP KOSONG (data jujur: Ketua Media memang belum ACC)
 *  - jejak override (siapa, kapan, alasan) + catatan riwayat tersimpan
 *  - Ketua Media & tim tetap dikabari dengan pesan khusus (menyebut override)
 *  - tampilan: tombol muncul untuk Pembina, tidak untuk Ketua Media; penanda muncul setelah override
 */
class ApproveOverrideTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.waha.enabled' => true]);
        Http::fake(['*' => Http::response(['id' => 'pesan-uji'], 200)]);

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

    private function anggota(string $divisi): User
    {
        return User::where('division', $divisi)->where('division_role', 'anggota')
            ->where('status', 'Aktif')->firstOrFail();
    }

    private function buatProject(string $status, array $anggotaIds): Project
    {
        $project = Project::create([
            'name'       => 'Project Override ' . uniqid(),
            'start_date' => Carbon::today()->toDateString(),
            'deadline'   => Carbon::today()->addDays(5)->toDateString(),
            'status'     => $status,
        ]);

        $project->users()->attach($anggotaIds);

        return $project;
    }

    private function alasanValid(): string
    {
        return 'Pengerjaan sudah selesai dan deadline dekat, Ketua Media belum bisa ACC.';
    }

    // ==========================================================
    // ALUR UTAMA
    // ==========================================================

    public function test_pembina_bisa_setujui_dan_lanjut_tanpa_menunggu_ketua_media(): void
    {
        $pembina = $this->pembina();
        $anggota = $this->anggota('Editing');

        $project = $this->buatProject(Project::STATUS_REVIEW, [$anggota->id]);

        $res = $this->actingAs($pembina)->patch("/projects/{$project->id}/approve-override", [
            'note' => $this->alasanValid(),
        ]);

        $res->assertRedirect(route('projects.show', $project->id));
        $res->assertSessionHas('success');

        $project->refresh();

        $this->assertSame(Project::STATUS_UPLOAD, $project->status, 'Project harus langsung masuk tahap Upload');
        $this->assertNotNull($project->approved_by_pembina_at, 'ACC Pembina ikut tercatat');
    }

    public function test_acc_ketua_media_tetap_kosong_supaya_data_jujur(): void
    {
        $pembina = $this->pembina();
        $anggota = $this->anggota('Editing');

        $project = $this->buatProject(Project::STATUS_REVIEW, [$anggota->id]);

        $this->actingAs($pembina)->patch("/projects/{$project->id}/approve-override", [
            'note' => $this->alasanValid(),
        ]);

        $this->assertNull(
            $project->fresh()->approved_by_ketua_at,
            'Ketua Media memang belum ACC — kolomnya TIDAK boleh diisi'
        );
    }

    public function test_jejak_override_tersimpan_lengkap(): void
    {
        $pembina = $this->pembina();
        $anggota = $this->anggota('Broadcast');

        $project = $this->buatProject(Project::STATUS_REVIEW, [$anggota->id]);

        $alasan = 'Deadline tinggal 2 hari dan Ketua Media belum bisa dihubungi, saya lanjutkan.';

        $this->actingAs($pembina)->patch("/projects/{$project->id}/approve-override", [
            'note' => $alasan,
        ]);

        $project->refresh();

        $this->assertSame($pembina->id, (int) $project->approved_override_by);
        $this->assertNotNull($project->approved_override_at);
        $this->assertSame($alasan, $project->approved_override_note);
        $this->assertTrue($project->dilanjutkan_tanpa_ketua_media);

        // Jejak juga masuk riwayat project (ProjectRevision)
        $revisi = ProjectRevision::where('project_id', $project->id)
            ->where('type', 'approve_override')
            ->first();

        $this->assertNotNull($revisi, 'Jejak override harus masuk riwayat project');
        $this->assertSame($pembina->id, (int) $revisi->user_id);
        $this->assertStringContainsString($alasan, $revisi->note);
    }

    public function test_revisi_terbuka_ikut_ditutup(): void
    {
        $pembina = $this->pembina();
        $anggota = $this->anggota('Editing');

        $project = $this->buatProject(Project::STATUS_REVIEW, [$anggota->id]);

        ProjectRevision::create([
            'project_id' => $project->id,
            'user_id'    => $pembina->id,
            'type'       => 'revision',
            'note'       => 'Perbaiki audio',
        ]);

        $this->actingAs($pembina)->patch("/projects/{$project->id}/approve-override", [
            'note' => $this->alasanValid(),
        ]);

        $this->assertSame(
            0,
            ProjectRevision::where('project_id', $project->id)->whereNull('resolved_at')->count(),
            'Revisi terbuka harus ditutup saat project dilanjutkan'
        );
    }

    // ==========================================================
    // ALASAN WAJIB
    // ==========================================================

    public function test_alasan_wajib_diisi(): void
    {
        $pembina = $this->pembina();
        $project = $this->buatProject(Project::STATUS_REVIEW, [$this->anggota('Editing')->id]);

        $res = $this->actingAs($pembina)->patch("/projects/{$project->id}/approve-override", []);

        $res->assertSessionHasErrors('note');
        $this->assertSame(Project::STATUS_REVIEW, $project->fresh()->status, 'Status tidak boleh berubah');
    }

    public function test_alasan_minimal_10_karakter(): void
    {
        $pembina = $this->pembina();
        $project = $this->buatProject(Project::STATUS_REVIEW, [$this->anggota('Editing')->id]);

        $res = $this->actingAs($pembina)->patch("/projects/{$project->id}/approve-override", [
            'note' => 'causa',
        ]);

        $res->assertSessionHasErrors('note');
        $this->assertSame(Project::STATUS_REVIEW, $project->fresh()->status);
    }

    // ==========================================================
    // HAK AKSES & STATUS
    // ==========================================================

    public function test_ketua_media_tidak_bisa_pakai_opsi_override(): void
    {
        $project = $this->buatProject(Project::STATUS_REVIEW, [$this->anggota('Editing')->id]);

        $res = $this->actingAs($this->ketua())->patch("/projects/{$project->id}/approve-override", [
            'note' => $this->alasanValid(),
        ]);

        $res->assertSessionHas('error');
        $this->assertSame(Project::STATUS_REVIEW, $project->fresh()->status);
        $this->assertNull($project->fresh()->approved_override_at);
    }

    public function test_kepala_divisi_dan_anggota_tidak_bisa_pakai_opsi_override(): void
    {
        $project = $this->buatProject(Project::STATUS_REVIEW, [$this->anggota('Editing')->id]);

        foreach ([
            User::where('division_role', 'kepala_divisi')->firstOrFail(),
            $this->anggota('Editing'),
        ] as $user) {
            $this->actingAs($user)
                ->patch("/projects/{$project->id}/approve-override", ['note' => $this->alasanValid()])
                ->assertSessionHas('error');
        }

        $this->assertSame(Project::STATUS_REVIEW, $project->fresh()->status);
    }

    public function test_hanya_bisa_dipakai_saat_tahap_review(): void
    {
        $pembina = $this->pembina();
        $project  = $this->buatProject(Project::STATUS_ON_PROGRESS, [$this->anggota('Editing')->id]);

        $res = $this->actingAs($pembina)->patch("/projects/{$project->id}/approve-override", [
            'note' => $this->alasanValid(),
        ]);

        $res->assertSessionHas('error');
        $this->assertSame(Project::STATUS_ON_PROGRESS, $project->fresh()->status);
        $this->assertNull($project->fresh()->approved_override_at);
    }

    // ==========================================================
    // NOTIFIKASI KHUSUS
    // ==========================================================

    public function test_ketua_media_dan_tim_dikabari_dengan_pesan_khusus(): void
    {
        $pembina = $this->pembina();
        $ketua   = $this->ketua();
        $anggota = $this->anggota('Editing');

        $project = $this->buatProject(Project::STATUS_REVIEW, [$anggota->id]);

        $alasan = 'Pengerjaan sudah kelar, deadline mepet, Ketua Media belum ACC.';

        $this->actingAs($pembina)->patch("/projects/{$project->id}/approve-override", [
            'note' => $alasan,
        ]);

        $logs = NotificationLog::where('type', 'project_approved_override')
            ->where('status', 'sent')
            ->get();

        $penerima = $logs->pluck('user_id')->unique()->values()->all();

        $this->assertContains($ketua->id, $penerima, 'Ketua Media harus tetap dikabari');
        $this->assertContains($anggota->id, $penerima, 'Anggota project harus dikabari');
        $this->assertNotContains($pembina->id, $penerima, 'Pelaku tidak dikirimi notifikasi aksinya sendiri');

        $pesan = $logs->first()->message;
        $this->assertStringContainsString('Tanpa Menunggu ACC Ketua Media', $pesan);
        $this->assertStringContainsString($alasan, $pesan, 'Alasan harus ikut terkirim');
        $this->assertStringContainsString($pembina->name, $pesan, 'Siapa yang melanjutkan harus disebut');
    }

    // ==========================================================
    // TAMPILAN
    // ==========================================================

    public function test_tombol_override_muncul_untuk_pembina_saja(): void
    {
        $project = $this->buatProject(Project::STATUS_REVIEW, [$this->anggota('Editing')->id]);

        $this->actingAs($this->pembina())->get("/projects/{$project->id}")
            ->assertOk()
            ->assertSee('Setujui & Lanjut Sekarang');

        $this->actingAs($this->ketua())->get("/projects/{$project->id}")
            ->assertOk()
            ->assertDontSee('Setujui & Lanjut Sekarang');
    }

    public function test_penanda_override_tampil_dan_ketua_media_tetap_belum_acc(): void
    {
        $pembina = $this->pembina();
        $anggota = $this->anggota('Editing');

        $project = $this->buatProject(Project::STATUS_REVIEW, [$anggota->id]);

        $alasan = 'Deadline mepet, saya lanjutkan supaya tim bisa upload.';

        $this->actingAs($pembina)->patch("/projects/{$project->id}/approve-override", [
            'note' => $alasan,
        ]);

        $res = $this->actingAs($pembina)->get("/projects/{$project->id}");

        $res->assertOk();
        $res->assertSee('Dilanjutkan oleh Pembina (tanpa ACC Ketua Media)');
        $res->assertSee($alasan);
        $res->assertSee($pembina->name);

        // ⚡ Ketua Media harus tetap tertulis "Belum ACC" — tidak boleh seolah sudah di-ACC
        $res->assertSee('Ketua Media: Belum ACC');
        $res->assertSee('Pembina: APPROVED');
    }
}
