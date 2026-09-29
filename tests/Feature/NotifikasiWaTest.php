<?php

namespace Tests\Feature;

use App\Models\Meeting;
use App\Models\NotificationLog;
use App\Models\Project;
use App\Models\ProjectTask;
use App\Models\User;
use App\Services\WhatsappNotifier;
use Carbon\Carbon;
use Database\Seeders\TestAccountSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * ⚡ PERBAIKAN NOTIFIKASI WA (29 Sep 2026)
 *
 * Keluhan Zev: "ada pesan yang seharusnya tidak terkirim ke anggota tersebut malah terkirim".
 *
 * Yang dikunci tes ini:
 *  P1 reminder H-3/H-1/overdue TIDAK lagi ke kepala divisi yang tidak terlibat
 *  P2 notifikasi "project di-ACC" TIDAK lagi ke semua kepala divisi
 *  P3 notulen rapat TIDAK ke user yang sudah non-aktif
 *  P4 pesan rapat/action item yang identik tidak dikirim ulang
 *  P5 kejadian berulang (revisi kedua dst) TETAP terkirim, sementara reminder tetap sekali saja
 *  P6 Ketua Media ikut menerima notifikasi jalur project
 *  P7 job mati (NotifyProjectCreated) sudah dihapus
 */
class NotifikasiWaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // WAHA "hidup" + HTTP dipalsukan, supaya pengiriman tercatat 'sent' di notification_logs
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

    private function kadiv(string $divisi): User
    {
        return User::where('division', $divisi)->where('division_role', 'kepala_divisi')
            ->where('status', 'Aktif')->firstOrFail();
    }

    private function anggota(string $divisi): User
    {
        return User::where('division', $divisi)->where('division_role', 'anggota')
            ->where('status', 'Aktif')->firstOrFail();
    }

    private function buatProject(string $status, array $anggotaIds, ?string $deadline = null): Project
    {
        $project = Project::create([
            'name'       => 'Project Notif ' . uniqid(),
            'start_date' => Carbon::today()->toDateString(),
            'deadline'   => $deadline ?? Carbon::today()->addDays(5)->toDateString(),
            'status'     => $status,
        ]);

        $project->users()->attach($anggotaIds);

        return $project;
    }

    private function buatTask(Project $project, int $assignedTo): ProjectTask
    {
        $task = ProjectTask::create([
            'project_id'  => $project->id,
            'assigned_to' => $assignedTo,
            'title'       => 'Task Notif ' . uniqid(),
            'task_date'   => Carbon::today()->toDateString(),
            'status'      => 'Pending',
            'weight'      => 5,
            'is_approved' => true,
            'scored_at'   => now(),
        ]);

        $task->contributors()->attach([$assignedTo]);

        return $task;
    }

    /** Siapa saja yang BENAR-BENAR menerima notifikasi jenis ini (status sent)? */
    private function penerima(string $type): array
    {
        return NotificationLog::where('type', $type)
            ->where('status', 'sent')
            ->pluck('user_id')
            ->unique()
            ->values()
            ->all();
    }

    private function jumlahLog(string $type, ?int $userId = null): int
    {
        $q = NotificationLog::where('type', $type);
        if ($userId) {
            $q->where('user_id', $userId);
        }

        return $q->count();
    }

    // ==========================================================
    // P1 — REMINDER HANYA KE YANG TERLIBAT
    // ==========================================================

    public function test_reminder_overdue_tidak_lagi_ke_kepala_divisi_yang_tidak_terlibat(): void
    {
        $kadivBroadcast  = $this->kadiv('Broadcast');
        $anggotaBroadcast = $this->anggota('Broadcast');

        // Project milik divisi Broadcast yang sudah lewat deadline
        $this->buatProject(
            Project::STATUS_ON_PROGRESS,
            [$kadivBroadcast->id, $anggotaBroadcast->id],
            Carbon::today()->subDay()->toDateString()
        );

        $this->artisan('reminders:send')->assertSuccessful();

        $penerima = $this->penerima('overdue');

        $this->assertContains($anggotaBroadcast->id, $penerima, 'Anggota project harus menerima');
        $this->assertContains($kadivBroadcast->id, $penerima, 'Kepala divisi TERKAIT harus menerima');
        $this->assertContains($this->ketua()->id, $penerima, 'Ketua Media ikut sebagai pemantau');

        // ⚡ Inti perbaikan: kepala divisi LAIN tidak boleh menerima
        foreach (['Lapangan', 'Editing', 'AI Research'] as $divisi) {
            $this->assertNotContains(
                $this->kadiv($divisi)->id,
                $penerima,
                "Kepala divisi {$divisi} TIDAK terlibat — tidak boleh menerima reminder overdue"
            );
        }

        $this->assertNotContains($this->anggota('Lapangan')->id, $penerima, 'Anggota divisi lain tidak boleh menerima');
        $this->assertNotContains($this->pembina()->id, $penerima);
    }

    public function test_reminder_h3_hanya_ke_anggota_project_dan_ketua_media(): void
    {
        $anggotaEditing = $this->anggota('Editing');

        $this->buatProject(
            Project::STATUS_ON_PROGRESS,
            [$anggotaEditing->id],
            Carbon::today()->addDays(3)->toDateString()
        );

        $this->artisan('reminders:send')->assertSuccessful();

        $penerima = $this->penerima('reminder_h3');

        $this->assertContains($anggotaEditing->id, $penerima);
        $this->assertContains($this->ketua()->id, $penerima);
        $this->assertNotContains($this->kadiv('Broadcast')->id, $penerima);
        $this->assertNotContains($this->kadiv('Lapangan')->id, $penerima);
    }

    // ==========================================================
    // P2 — PROJECT DI-ACC HANYA KE YANG TERLIBAT
    // ==========================================================

    public function test_notifikasi_project_disetujui_tidak_lagi_ke_semua_kepala_divisi(): void
    {
        $kadivEditing   = $this->kadiv('Editing');
        $anggotaEditing = $this->anggota('Editing');

        $project = $this->buatProject(Project::STATUS_REVIEW, [$kadivEditing->id, $anggotaEditing->id]);

        $this->actingAs($this->pembina())->patch("/projects/{$project->id}/approve");
        $this->actingAs($this->ketua())->patch("/projects/{$project->id}/approve");

        $penerima = $this->penerima('project_approved');

        $this->assertContains($kadivEditing->id, $penerima, 'Kepala divisi yang TERLIBAT harus menerima');
        $this->assertContains($anggotaEditing->id, $penerima);

        // ⚡ Inti perbaikan
        foreach (['Lapangan', 'Broadcast', 'AI Research'] as $divisi) {
            $this->assertNotContains(
                $this->kadiv($divisi)->id,
                $penerima,
                "Kepala divisi {$divisi} tidak terlibat project ini — tidak boleh menerima notif ACC"
            );
        }

        // Ketua Media adalah pelaku ACC kedua -> tidak dikirimi notifikasi aksinya sendiri
        $this->assertNotContains($this->ketua()->id, $penerima);
    }

    // ==========================================================
    // P6 — KETUA MEDIA IKUT DI JALUR PROJECT
    // ==========================================================

    public function test_ketua_media_ikut_menerima_notifikasi_project_dirilis(): void
    {
        $anggotaEditing = $this->anggota('Editing');
        $kadivLapangan  = $this->kadiv('Lapangan');

        $project = $this->buatProject(Project::STATUS_SCORING, [$anggotaEditing->id]);
        $this->buatTask($project, $anggotaEditing->id);

        $this->actingAs($this->pembina())->patch("/projects/{$project->id}/release");

        $penerima = $this->penerima('project_released');

        $this->assertContains($this->ketua()->id, $penerima, 'Ketua Media harus ikut dikabari saat project dirilis');
        $this->assertContains($anggotaEditing->id, $penerima);
        $this->assertNotContains($this->pembina()->id, $penerima, 'Pelaku rilis tidak dikirimi notifikasi aksinya sendiri');
        $this->assertNotContains($kadivLapangan->id, $penerima, 'Kepala divisi di luar project tidak boleh menerima');
    }

    // ==========================================================
    // P5 — KEJADIAN BERULANG TETAP TERKIRIM, REMINDER SEKALI SAJA
    // ==========================================================

    public function test_kejadian_berulang_tetap_terkirim_setelah_jendela_anti_dobel(): void
    {
        $notifier = app(WhatsappNotifier::class);
        $user     = $this->anggota('Editing');
        $project  = $this->buatProject(Project::STATUS_REVIEW, [$user->id]);
        $pesan    = 'Catatan revisi babak pertama';

        $this->assertTrue($notifier->sendAndLog($user, $project->id, 'revision_requested', $pesan));
        $this->assertSame(1, $this->jumlahLog('revision_requested'));

        // Klik/proses dobel langsung -> tertahan (anti dobel 30 menit)
        $notifier->sendAndLog($user, $project->id, 'revision_requested', $pesan);
        $this->assertSame(1, $this->jumlahLog('revision_requested'), 'Dobel dalam jendela pendek harus ditahan');

        // Setelah jendela lewat -> revisi babak KEDUA harus tetap terkirim
        NotificationLog::where('type', 'revision_requested')->update(['created_at' => now()->subHours(2)]);

        $this->assertTrue($notifier->sendAndLog($user, $project->id, 'revision_requested', $pesan));
        $this->assertSame(2, $this->jumlahLog('revision_requested'), 'Kejadian berulang WAJIB tetap terkirim');
    }

    public function test_reminder_tetap_hanya_sekali_selamanya(): void
    {
        $notifier = app(WhatsappNotifier::class);
        $user     = $this->anggota('Lapangan');
        $project  = $this->buatProject(Project::STATUS_ON_PROGRESS, [$user->id]);
        $pesan    = 'Reminder H-3 uji coba';

        $notifier->sendAndLog($user, $project->id, 'reminder_h3', $pesan);
        $this->assertSame(1, $this->jumlahLog('reminder_h3'));

        // Walau sudah berhari-hari, reminder yang sama TIDAK dikirim lagi
        NotificationLog::where('type', 'reminder_h3')->update(['created_at' => now()->subDays(5)]);
        $notifier->sendAndLog($user, $project->id, 'reminder_h3', $pesan);

        $this->assertSame(1, $this->jumlahLog('reminder_h3'), 'Reminder cukup sekali saja per project');
    }

    // ==========================================================
    // P3 — NOTULEN TIDAK KE USER NON-AKTIF
    // ==========================================================

    public function test_notulen_rapat_tidak_dikirim_ke_user_non_aktif(): void
    {
        $nonAktif = $this->kadiv('AI Research');
        $nonAktif->update(['status' => 'Nonaktif']);

        $meeting = Meeting::create([
            'title'        => 'Rapat Uji Notulen',
            'meeting_date' => Carbon::today()->toDateString(),
            'start_time'   => '08:00',
            'end_time'     => '09:00',
            'location'     => 'Aula',
            'status'       => 'draft',
            'created_by'   => $this->pembina()->id,
        ]);

        $this->actingAs($this->pembina())->patch("/meetings/{$meeting->id}/publish");

        $penerima = $this->penerima('meeting_published');

        $this->assertNotEmpty($penerima, 'Pemimpin aktif harus menerima notulen');
        $this->assertContains($this->ketua()->id, $penerima);
        $this->assertContains($this->kadiv('Lapangan')->id, $penerima);
        $this->assertNotContains($nonAktif->id, $penerima, 'User non-aktif TIDAK boleh menerima notulen');
    }

    public function test_notulen_pemimpin_tanpa_nomor_wa_dilewati(): void
    {
        $tanpaNomor = $this->kadiv('Broadcast');
        $tanpaNomor->update(['phone' => null]);

        $meeting = Meeting::create([
            'title'        => 'Rapat Uji Tanpa Nomor',
            'meeting_date' => Carbon::today()->toDateString(),
            'start_time'   => '08:00',
            'end_time'     => '09:00',
            'location'     => 'Aula',
            'status'       => 'draft',
            'created_by'   => $this->pembina()->id,
        ]);

        $this->actingAs($this->pembina())->patch("/meetings/{$meeting->id}/publish");

        $this->assertNotContains($tanpaNomor->id, $this->penerima('meeting_published'));
    }

    // ==========================================================
    // P4 — PESAN IDENTIK TIDAK DIKIRIM ULANG
    // ==========================================================

    public function test_action_item_rapat_tidak_dikirim_ulang_kalau_dipublikasikan_lagi(): void
    {
        $penerimaTugas = $this->anggota('Lapangan');

        $meeting = Meeting::create([
            'title'        => 'Rapat Uji Action Item',
            'meeting_date' => Carbon::today()->toDateString(),
            'start_time'   => '08:00',
            'end_time'     => '09:00',
            'location'     => 'Aula',
            'status'       => 'draft',
            'created_by'   => $this->pembina()->id,
            'action_items' => [
                ['task' => 'Siapkan dokumentasi', 'assigned_to' => $penerimaTugas->id, 'deadline' => '2026-10-01', 'status' => 'pending'],
            ],
        ]);

        $this->actingAs($this->pembina())->patch("/meetings/{$meeting->id}/publish");
        $this->assertSame(1, $this->jumlahLog('meeting_action_item', $penerimaTugas->id));

        // Rapat dibuka lagi jadi draft lalu dipublikasikan ulang (pesan masih sama)
        $meeting->update(['status' => 'draft']);
        $this->actingAs($this->pembina())->patch("/meetings/{$meeting->id}/publish");

        $this->assertSame(
            1,
            $this->jumlahLog('meeting_action_item', $penerimaTugas->id),
            'Pesan action item yang isinya SAMA tidak boleh dikirim dua kali'
        );
    }

    public function test_pesan_tanpa_project_yang_berbeda_tetap_terkirim(): void
    {
        $notifier = app(WhatsappNotifier::class);
        $user     = $this->anggota('Editing');

        $notifier->sendAndLog($user, null, 'meeting_action_item', 'Tugas A');
        $notifier->sendAndLog($user, null, 'meeting_action_item', 'Tugas B');

        $this->assertSame(2, $this->jumlahLog('meeting_action_item', $user->id), 'Pesan yang isinya BEDA tetap terkirim');
    }

    // ==========================================================
    // P7 — JOB MATI SUDAH DIHAPUS
    // ==========================================================

    public function test_job_notifikasi_lama_yang_tidak_dipakai_sudah_dihapus(): void
    {
        // Cek file-nya (bukan class_exists) supaya tidak memicu autoload ke file yang sudah hilang
        $this->assertFileDoesNotExist(
            app_path('Jobs/NotifyProjectCreated.php'),
            'Job mati NotifyProjectCreated harus sudah dihapus supaya tidak jadi ranjau'
        );

        // Dan tidak ada rujukan tersisa di kode aplikasi
        $rujukan = [];
        foreach (['app', 'routes'] as $folder) {
            $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(base_path($folder)));
            foreach ($iterator as $file) {
                if ($file->isFile() && $file->getExtension() === 'php'
                    && str_contains(file_get_contents($file->getPathname()), 'NotifyProjectCreated')) {
                    $rujukan[] = $file->getPathname();
                }
            }
        }

        $this->assertSame([], $rujukan, 'Masih ada kode yang merujuk job mati: ' . implode(', ', $rujukan));
    }

    // ==========================================================
    // TAMBAHAN: user tanpa nomor WA tidak pernah dicatat 'sent'
    // ==========================================================

    public function test_user_tanpa_nomor_wa_tidak_dikirimi(): void
    {
        $notifier = app(WhatsappNotifier::class);
        $user     = $this->anggota('Lapangan');
        $user->update(['phone' => null]);

        $project = $this->buatProject(Project::STATUS_ON_PROGRESS, [$user->id]);

        $this->assertFalse($notifier->sendAndLog($user, $project->id, 'project_released', 'Uji'));
        $this->assertSame(0, $this->jumlahLog('project_released', $user->id));
    }
}
