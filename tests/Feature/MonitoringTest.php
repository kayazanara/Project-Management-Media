<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\ProjectTask;
use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\TestAccountSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * REGRESI BUG MONITORING TIM (28 Sep 2026):
 *  M1. On-time rate selalu 0% (membandingkan datetime dengan date) di 4 titik
 *  M2. Workload Balance tidak mengikuti filter periode + hanya pakai assigned_to
 *  M3. Ringkasan Divisi: total poin ikut menghitung task yang belum selesai
 *  M4. Alert "belum dapat poin" pakai aturan poin lama (tanpa status Completed)
 *  M5. Alert "divisi turun" pakai assigned_to + tanpa filter status Aktif
 */
class MonitoringTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Http::fake();
        $this->seed(TestAccountSeeder::class);
    }

    private function pembina(): User
    {
        return User::where('division_role', 'pembina')->where('status', 'Aktif')->firstOrFail();
    }

    private function anggotaDivisi(string $divisi): User
    {
        return User::where('division', 'like', "%{$divisi}%")
            ->where('division_role', 'anggota')
            ->where('status', 'Aktif')
            ->firstOrFail();
    }

    private function buatTask(
        array $kontributorIds,
        int $picId,
        float $weight,
        string $status,
        ?string $taskDate = null,
        ?string $completedAt = null
    ): ProjectTask {
        $project = Project::create([
            'name'       => 'Project Monitoring ' . uniqid(),
            'start_date' => Carbon::today()->toDateString(),
            'deadline'   => Carbon::today()->addDays(7)->toDateString(),
            'status'     => Project::STATUS_ON_PROGRESS,
        ]);

        $task = ProjectTask::create([
            'project_id'   => $project->id,
            'assigned_to'  => $picId,
            'title'        => 'Task Monitoring ' . uniqid(),
            'task_date'    => $taskDate ?? Carbon::today()->toDateString(),
            'status'       => $status,
            'weight'       => $weight,
            'is_approved'  => true,
            'scored_at'    => now(),
            'completed_at' => $completedAt,
        ]);

        $task->contributors()->attach($kontributorIds);

        return $task;
    }

    /** Ambil data view halaman Monitoring sebagai Pembina. */
    private function monitoring(array $query = []): array
    {
        $res = $this->actingAs($this->pembina())->get('/monitoring?' . http_build_query($query ?: ['days' => 30]));
        $res->assertOk();

        return [
            'members'         => $res->viewData('members'),
            'workload'        => $res->viewData('workload'),
            'deadline'        => $res->viewData('deadline'),
            'alerts'          => $res->viewData('alerts'),
            'divisionSummary' => $res->viewData('divisionSummary'),
        ];
    }

    // ==========================================================
    // M1 — ON-TIME PER TANGGAL
    // ==========================================================

    public function test_on_time_dihitung_per_tanggal_bukan_per_jam(): void
    {
        $anggota = $this->anggotaDivisi('Lapangan');

        // Selesai di HARI YANG SAMA dengan target, jam 06:00 -> harus ON TIME (dulu dianggap telat)
        $this->buatTask(
            [$anggota->id],
            $anggota->id,
            10,
            'Completed',
            Carbon::today()->toDateString(),
            Carbon::today()->setTime(6, 0)->toDateTimeString()
        );

        $data = $this->monitoring(['days' => 7]);

        $member = collect($data['members'])->firstWhere('id', $anggota->id);
        $this->assertSame(1, (int) $member['completed_tasks']);
        $this->assertSame(1, (int) $member['on_time_count'], 'Selesai di hari yang sama harus dihitung ON TIME.');
        $this->assertSame(0, (int) $member['late_count']);
        $this->assertSame(100, (int) $member['on_time_rate']);

        $deadline = collect($data['deadline'])->firstWhere('id', $anggota->id);
        $this->assertSame(1, (int) $deadline['on_time']);
        $this->assertSame(100, (int) $deadline['on_time_rate']);

        $divisi = collect($data['divisionSummary'])->firstWhere('name', 'Lapangan');
        $this->assertSame(100, (int) $divisi['on_time_rate']);
    }

    public function test_task_yang_benar_benar_telat_tetap_dihitung_telat(): void
    {
        $anggota = $this->anggotaDivisi('Editing');

        // Selesai 2 hari SETELAH target -> memang telat
        $this->buatTask(
            [$anggota->id],
            $anggota->id,
            10,
            'Completed',
            Carbon::today()->subDays(2)->toDateString(),
            Carbon::today()->setTime(9, 0)->toDateTimeString()
        );

        $member = collect($this->monitoring(['days' => 7])['members'])->firstWhere('id', $anggota->id);

        $this->assertSame(0, (int) $member['on_time_count']);
        $this->assertSame(1, (int) $member['late_count']);
        $this->assertSame(0, (int) $member['on_time_rate']);
    }

    // ==========================================================
    // M2 — WORKLOAD BALANCE
    // ==========================================================

    public function test_workload_balance_mengikuti_filter_periode(): void
    {
        $anggota = $this->anggotaDivisi('Broadcast');

        // Task terjadwal 60 hari lalu, belum selesai
        $this->buatTask([$anggota->id], $anggota->id, 5, 'Pending', Carbon::today()->subDays(60)->toDateString());

        $w7  = collect($this->monitoring(['days' => 7])['workload'])->firstWhere('id', $anggota->id);
        $w90 = collect($this->monitoring(['days' => 90])['workload'])->firstWhere('id', $anggota->id);

        $this->assertSame(0, (int) $w7['total_tasks'], 'Task 60 hari lalu tidak boleh masuk periode 7 hari.');
        $this->assertSame(1, (int) $w90['total_tasks'], 'Task 60 hari lalu harus masuk periode 90 hari.');
    }

    public function test_workload_menghitung_kontributor_bukan_hanya_pic(): void
    {
        $anggota = $this->anggotaDivisi('AI Research');
        $picLuar = User::where('division_role', 'ketua_media')->where('status', 'Aktif')->firstOrFail();

        $this->buatTask([$anggota->id], $picLuar->id, 5, 'Pending');

        $w = collect($this->monitoring(['days' => 7])['workload'])->firstWhere('id', $anggota->id);

        $this->assertSame(1, (int) $w['total_tasks'], 'Task harus dihitung untuk kontributornya, bukan hanya PIC.');
    }

    // ==========================================================
    // M3 — RINGKASAN DIVISI
    // ==========================================================

    public function test_ringkasan_divisi_hanya_menghitung_poin_task_selesai(): void
    {
        $anggota = $this->anggotaDivisi('Editing');

        // Bobot 20 tapi BELUM selesai -> tidak boleh menambah poin divisi
        $task = $this->buatTask([$anggota->id], $anggota->id, 20, 'Pending');

        $sebelum = collect($this->monitoring(['days' => 30])['divisionSummary'])->firstWhere('name', 'Editing');
        $this->assertSame(0, (int) $sebelum['total_points'], 'Task belum selesai tidak boleh memberi poin divisi.');

        $task->update(['status' => 'Completed', 'completed_at' => now()]);

        $sesudah = collect($this->monitoring(['days' => 30])['divisionSummary'])->firstWhere('name', 'Editing');
        $this->assertSame(20, (int) $sesudah['total_points']);
    }

    // ==========================================================
    // M4 — ALERT "BELUM DAPAT POIN"
    // ==========================================================

    public function test_alert_belum_dapat_poin_muncul_walau_punya_task_belum_selesai(): void
    {
        $anggota = $this->anggotaDivisi('Lapangan');

        // Punya task berbobot, tapi belum satu pun diselesaikan
        $this->buatTask([$anggota->id], $anggota->id, 8, 'Pending');

        $alerts = $this->monitoring(['days' => 30])['alerts'];

        $cocok = collect($alerts)->first(
            fn ($a) => $a['type'] === 'no_points' && str_contains($a['title'], $anggota->name)
        );

        $this->assertNotNull($cocok, 'Anggota tanpa task selesai harus muncul di alert "belum dapat poin".');
    }

    public function test_alert_belum_dapat_poin_tidak_muncul_kalau_tasknya_sudah_selesai(): void
    {
        $anggota = $this->anggotaDivisi('Broadcast');

        $this->buatTask(
            [$anggota->id],
            $anggota->id,
            8,
            'Completed',
            Carbon::today()->toDateString(),
            Carbon::today()->setTime(7, 0)->toDateTimeString()
        );

        $alerts = $this->monitoring(['days' => 30])['alerts'];

        $cocok = collect($alerts)->first(
            fn ($a) => $a['type'] === 'no_points' && str_contains($a['title'], $anggota->name)
        );

        $this->assertNull($cocok, 'Yang sudah menyelesaikan task berbobot tidak boleh kena alert ini.');
    }

    // ==========================================================
    // M5 — ALERT "DIVISI TURUN"
    // ==========================================================

    public function test_alert_divisi_turun_menghitung_kontributor(): void
    {
        $anggota = $this->anggotaDivisi('Broadcast');
        $picLuar = User::where('division_role', 'ketua_media')->where('status', 'Aktif')->firstOrFail();

        // Minggu lalu: 2 task selesai — kontributornya anggota Broadcast, PIC di luar divisi
        $hariLalu = Carbon::now()->subWeek()->startOfWeek()->addDays(2);

        foreach ([1, 2] as $i) {
            $this->buatTask(
                [$anggota->id],
                $picLuar->id,
                5,
                'Completed',
                $hariLalu->toDateString(),
                $hariLalu->copy()->setTime(9, $i)->toDateTimeString()
            );
        }

        // Minggu ini: tidak ada penyelesaian -> turun 100%
        $alerts = $this->monitoring(['days' => 30])['alerts'];

        $drop = collect($alerts)->first(
            fn ($a) => $a['type'] === 'division_drop' && str_contains($a['title'], 'Broadcast')
        );

        $this->assertNotNull($drop, 'Penurunan divisi harus terdeteksi dari aktivitas KONTRIBUTOR.');
        $this->assertStringContainsString('Minggu lalu: 2', $drop['message']);
    }

    // ==========================================================
    // FASE 2 — WORKLOAD BALANCE: poin, estimasi beban, ambang overload 65
    // ==========================================================

    public function test_workload_overload_kalau_poin_periode_mencapai_65(): void
    {
        $anggota = $this->anggotaDivisi('Lapangan');

        // 7 task selesai @bobot 10 = 70 poin (>= 65)
        for ($i = 0; $i < 7; $i++) {
            $this->buatTask(
                [$anggota->id],
                $anggota->id,
                10,
                'Completed',
                null,
                Carbon::today()->setTime(6, $i)->toDateTimeString()
            );
        }

        $w = collect($this->monitoring(['days' => 30])['workload'])->firstWhere('id', $anggota->id);

        $this->assertSame(70, (int) $w['points']);
        $this->assertSame('overload', $w['status'], 'Poin >= 65 harus berstatus Overload.');
    }

    public function test_workload_status_seimbang_ringan_dan_idle(): void
    {
        $seimbang = $this->anggotaDivisi('Editing');
        $ringan   = $this->anggotaDivisi('Broadcast');
        $idle     = User::where('division_role', 'ketua_media')->where('status', 'Aktif')->firstOrFail();

        // 3 task @10 = 30 poin -> Seimbang (20-64)
        for ($i = 0; $i < 3; $i++) {
            $this->buatTask(
                [$seimbang->id],
                $seimbang->id,
                10,
                'Completed',
                null,
                Carbon::today()->setTime(7, $i)->toDateTimeString()
            );
        }

        // 1 task @5 = 5 poin -> Ringan (< 20)
        $this->buatTask([$ringan->id], $ringan->id, 5, 'Completed', null, Carbon::today()->setTime(8, 0)->toDateTimeString());

        $workload = collect($this->monitoring(['days' => 30])['workload']);

        $this->assertSame('balanced', $workload->firstWhere('id', $seimbang->id)['status']);
        $this->assertSame('light', $workload->firstWhere('id', $ringan->id)['status']);
        $this->assertSame('idle', $workload->firstWhere('id', $idle->id)['status']);
    }

    public function test_estimasi_beban_menghitung_task_terjadwal_termasuk_yang_belum_selesai(): void
    {
        $anggota = $this->anggotaDivisi('AI Research');

        $this->buatTask([$anggota->id], $anggota->id, 10, 'Completed', null, Carbon::today()->setTime(6, 0)->toDateTimeString());
        $this->buatTask([$anggota->id], $anggota->id, 5, 'Pending');

        $w = collect($this->monitoring(['days' => 30])['workload'])->firstWhere('id', $anggota->id);

        $this->assertSame(15, (int) $w['planned_load'], 'Estimasi beban = semua bobot task yang dijadwalkan di periode.');
        $this->assertSame(10, (int) $w['points'], 'Poin hanya dari task yang sudah selesai.');
    }

    public function test_workload_mengurutkan_overload_paling_atas(): void
    {
        $overload = $this->anggotaDivisi('Lapangan');
        $kecil    = $this->anggotaDivisi('Editing');

        for ($i = 0; $i < 7; $i++) {
            $this->buatTask(
                [$overload->id],
                $overload->id,
                10,
                'Completed',
                null,
                Carbon::today()->setTime(6, $i)->toDateTimeString()
            );
        }

        $this->buatTask([$kecil->id], $kecil->id, 5, 'Completed', null, Carbon::today()->setTime(9, 0)->toDateTimeString());

        $workload = $this->monitoring(['days' => 30])['workload'];

        $this->assertSame('overload', $workload[0]['status'], 'Baris pertama harus yang Overload.');
        $this->assertSame($overload->id, $workload[0]['id']);
    }
}
