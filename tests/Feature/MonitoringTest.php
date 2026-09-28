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
            'directory'       => $res->viewData('directory'),
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

    public function test_tugas_yang_dibebankan_selalu_masuk_estimasi_beban(): void
    {
        $anggota = $this->anggotaDivisi('Broadcast');

        // (a) tugas lama (60 hari lalu) belum selesai, bobot 5
        $this->buatTask([$anggota->id], $anggota->id, 5, 'Pending', Carbon::today()->subDays(60)->toDateString());
        // (b) tugas bertanggal KE DEPAN (30 hari lagi), bobot 7 -> dulu tidak muncul sama sekali
        $this->buatTask([$anggota->id], $anggota->id, 7, 'Pending', Carbon::today()->addDays(30)->toDateString());

        $w7  = collect($this->monitoring(['days' => 7])['workload'])->firstWhere('id', $anggota->id);
        $w90 = collect($this->monitoring(['days' => 90])['workload'])->firstWhere('id', $anggota->id);

        // ⚡ Beban & tugas aktif tidak lagi dibatasi jendela periode
        $this->assertSame(2, (int) $w7['active_tasks'], 'Tugas yang dibebankan harus terlihat walau tanggalnya jauh/ke depan.');
        $this->assertSame(12, (int) $w7['planned_load'], 'Estimasi beban = 5 + 7 bobot.');
        $this->assertSame(12, (int) $w90['planned_load']);
    }

    public function test_poin_dihitung_dari_tanggal_selesai_bukan_tanggal_rencana(): void
    {
        $anggota = $this->anggotaDivisi('Lapangan');

        // Direncanakan 60 hari lalu, TAPI diselesaikan hari ini -> poin harus muncul di periode 7 hari
        $task = $this->buatTask([$anggota->id], $anggota->id, 10, 'Pending', Carbon::today()->subDays(60)->toDateString());
        $task->update(['status' => 'Completed', 'completed_at' => now()]);

        $w = collect($this->monitoring(['days' => 7])['workload'])->firstWhere('id', $anggota->id);

        $this->assertSame(1, (int) $w['completed_tasks'], 'Task selesai hari ini harus masuk periode 7 hari.');
        $this->assertSame(10, (int) $w['points'], 'Poin pakai tanggal SELESAI, bukan tanggal rencana.');
        $this->assertSame(0, (int) $w['planned_load'], 'Task yang sudah selesai tidak lagi jadi beban berjalan.');
    }

    public function test_bobot_task_bisa_diisi_langsung_saat_task_dibebankan(): void
    {
        $anggota = $this->anggotaDivisi('Editing');

        $contoh    = $this->buatTask([$anggota->id], $anggota->id, 5, 'Pending');
        $projectId = $contoh->project_id;

        $this->actingAs($this->pembina())
            ->post("/projects/{$projectId}/tasks", [
                'title'       => 'Task dengan bobot langsung',
                'assigned_to' => $anggota->id,
                'task_date'   => Carbon::today()->toDateString(),
                'weight'      => 7,
            ])
            ->assertRedirect();

        $task = ProjectTask::where('title', 'Task dengan bobot langsung')->firstOrFail();

        $this->assertSame(7, (int) $task->weight, 'Bobot harus tersimpan saat task dibebankan.');
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

    public function test_status_workload_berdasarkan_rasio_task_aktif(): void
    {
        $overload   = $this->anggotaDivisi('Lapangan');
        $mulaiBerat = $this->anggotaDivisi('Editing');
        $seimbang   = $this->anggotaDivisi('Broadcast');
        $ringan     = $this->anggotaDivisi('AI Research');
        $idle       = User::where('division_role', 'ketua_media')->where('status', 'Aktif')->firstOrFail();

        // 9 aktif : 1 selesai = 90% -> Overload
        for ($i = 0; $i < 9; $i++) {
            $this->buatTask([$overload->id], $overload->id, 5, 'Pending');
        }
        $this->buatTask([$overload->id], $overload->id, 5, 'Completed', null, Carbon::today()->setTime(6, 0)->toDateTimeString());

        // 7 aktif : 3 selesai = 70% -> Mulai Berat
        for ($i = 0; $i < 7; $i++) {
            $this->buatTask([$mulaiBerat->id], $mulaiBerat->id, 5, 'Pending');
        }
        for ($i = 0; $i < 3; $i++) {
            $this->buatTask([$mulaiBerat->id], $mulaiBerat->id, 5, 'Completed', null, Carbon::today()->setTime(7, $i)->toDateTimeString());
        }

        // 4 aktif : 6 selesai = 40% -> Seimbang
        for ($i = 0; $i < 4; $i++) {
            $this->buatTask([$seimbang->id], $seimbang->id, 5, 'Pending');
        }
        for ($i = 0; $i < 6; $i++) {
            $this->buatTask([$seimbang->id], $seimbang->id, 5, 'Completed', null, Carbon::today()->setTime(8, $i)->toDateTimeString());
        }

        // 2 aktif : 8 selesai = 20% -> Ringan
        for ($i = 0; $i < 2; $i++) {
            $this->buatTask([$ringan->id], $ringan->id, 5, 'Pending');
        }
        for ($i = 0; $i < 8; $i++) {
            $this->buatTask([$ringan->id], $ringan->id, 5, 'Completed', null, Carbon::today()->setTime(9, $i)->toDateTimeString());
        }

        $workload = collect($this->monitoring(['days' => 30])['workload']);

        $this->assertSame('overload', $workload->firstWhere('id', $overload->id)['status'], 'Rasio 90% = Overload');
        $this->assertSame('starting_heavy', $workload->firstWhere('id', $mulaiBerat->id)['status'], 'Rasio 70% = Mulai Berat');
        $this->assertSame('balanced', $workload->firstWhere('id', $seimbang->id)['status'], 'Rasio 40% = Seimbang');
        $this->assertSame('light', $workload->firstWhere('id', $ringan->id)['status'], 'Rasio 20% = Ringan');
        $this->assertSame('idle', $workload->firstWhere('id', $idle->id)['status'], 'Tanpa task = Idle');
    }

    public function test_estimasi_beban_menghitung_task_terjadwal_termasuk_yang_belum_selesai(): void
    {
        $anggota = $this->anggotaDivisi('AI Research');

        $this->buatTask([$anggota->id], $anggota->id, 10, 'Completed', null, Carbon::today()->setTime(6, 0)->toDateTimeString());
        $this->buatTask([$anggota->id], $anggota->id, 5, 'Pending');

        $w = collect($this->monitoring(['days' => 30])['workload'])->firstWhere('id', $anggota->id);

        $this->assertSame(5, (int) $w['planned_load'], 'Estimasi beban = bobot tugas yang DIBEBANKAN & belum selesai.');
        $this->assertSame(10, (int) $w['points'], 'Poin hanya dari task yang sudah selesai.');
    }

    public function test_urutan_workload_paling_berat_dulu(): void
    {
        $berat = $this->anggotaDivisi('Lapangan');
        $kecil = $this->anggotaDivisi('Editing');

        // 9 aktif : 1 selesai = 90% -> Overload
        for ($i = 0; $i < 9; $i++) {
            $this->buatTask([$berat->id], $berat->id, 5, 'Pending');
        }
        $this->buatTask([$berat->id], $berat->id, 5, 'Completed', null, Carbon::today()->setTime(6, 0)->toDateTimeString());

        // 0 aktif : 1 selesai = 0% -> Ringan
        $this->buatTask([$kecil->id], $kecil->id, 5, 'Completed', null, Carbon::today()->setTime(7, 0)->toDateTimeString());

        $workload = $this->monitoring(['days' => 30])['workload'];

        $this->assertSame('overload', $workload[0]['status'], 'Baris pertama harus yang paling berat.');
        $this->assertSame($berat->id, $workload[0]['id']);
    }

    public function test_rincian_bobot_berisi_daftar_task_yang_dibebankan(): void
    {
        $anggota = $this->anggotaDivisi('Lapangan');

        $this->buatTask([$anggota->id], $anggota->id, 5, 'Pending');
        $this->buatTask([$anggota->id], $anggota->id, 7, 'Pending');
        // yang sudah selesai TIDAK boleh ikut jadi beban
        $this->buatTask([$anggota->id], $anggota->id, 9, 'Completed', null, Carbon::today()->setTime(6, 0)->toDateTimeString());

        $w = collect($this->monitoring(['days' => 30])['workload'])->firstWhere('id', $anggota->id);

        $this->assertSame(12, (int) $w['planned_load'], 'Beban = 5 + 7 bobot.');
        $this->assertCount(2, $w['beban_items'], 'Hanya tugas yang belum selesai yang masuk rincian beban.');
        $this->assertSame(
            [5.0, 7.0],
            collect($w['beban_items'])->pluck('weight')->map(fn ($x) => (float) $x)->sort()->values()->all()
        );
        $this->assertArrayHasKey('project', $w['beban_items'][0]);
        $this->assertArrayHasKey('task_date', $w['beban_items'][0]);
        $this->assertArrayHasKey('status', $w['beban_items'][0]);
    }

    // ==========================================================
    // FASE 3 — TEAM DIRECTORY: online/offline + riwayat poin
    // ==========================================================

    private function catatSesi(User $user, int $detikLalu = 0): void
    {
        \DB::table('sessions')->insert([
            'id'            => 'uji-' . uniqid(),
            'user_id'       => $user->id,
            'ip_address'    => '127.0.0.1',
            'user_agent'    => 'phpunit',
            'payload'       => base64_encode('x'),
            'last_activity' => now()->subSeconds($detikLalu)->timestamp,
        ]);
    }

    public function test_direktori_menampilkan_status_online_dari_aktivitas_sesi(): void
    {
        $online  = $this->anggotaDivisi('Lapangan');
        $offline = $this->anggotaDivisi('Editing');

        $this->catatSesi($online); // aktivitas baru saja

        $direktori = collect($this->monitoring(['days' => 30])['directory']);

        $this->assertTrue((bool) $direktori->firstWhere('id', $online->id)['is_online'], 'Ada sesi < 5 menit -> Online.');
        $this->assertFalse((bool) $direktori->firstWhere('id', $offline->id)['is_online'], 'Tanpa sesi aktif -> Offline.');
    }

    public function test_direktori_online_hanya_kalau_aktivitas_dalam_5_menit(): void
    {
        $lama = $this->anggotaDivisi('Broadcast');

        $this->catatSesi($lama, 30 * 60); // 30 menit lalu

        $row = collect($this->monitoring(['days' => 30])['directory'])->firstWhere('id', $lama->id);

        $this->assertFalse((bool) $row['is_online'], 'Aktivitas 30 menit lalu bukan Online.');
        $this->assertStringContainsString('menit lalu', $row['last_seen']);
    }

    public function test_endpoint_riwayat_poin_hanya_untuk_pembina(): void
    {
        $anggota = $this->anggotaDivisi('Lapangan');
        $ketua   = User::where('division_role', 'ketua_media')->where('status', 'Aktif')->firstOrFail();

        $this->actingAs($ketua)
            ->get("/monitoring/point-history/{$anggota->id}")
            ->assertForbidden();

        $this->actingAs($this->pembina())
            ->get("/monitoring/point-history/{$anggota->id}")
            ->assertOk();
    }

    public function test_riwayat_poin_berisi_task_penyumbang_poin_dan_dibagi_rata(): void
    {
        $anggota = $this->anggotaDivisi('AI Research');
        $teman   = $this->anggotaDivisi('Lapangan');

        // Bobot 10 dibagi 2 kontributor -> 5 poin
        $this->buatTask([$anggota->id, $teman->id], $anggota->id, 10, 'Completed', null, Carbon::today()->setTime(6, 0)->toDateTimeString());
        // Bobot 10 dikerjakan sendiri -> 10 poin
        $this->buatTask([$anggota->id], $anggota->id, 10, 'Completed', null, Carbon::today()->setTime(7, 0)->toDateTimeString());
        // Belum selesai -> tidak boleh muncul
        $this->buatTask([$anggota->id], $anggota->id, 99, 'Pending');

        $res = $this->actingAs($this->pembina())->get("/monitoring/point-history/{$anggota->id}?days=30");
        $res->assertOk();

        $data  = $res->json();
        $items = collect($data['items']);

        $this->assertCount(2, $items, 'Hanya task selesai yang masuk riwayat poin.');
        $this->assertSame([5.0, 10.0], $items->pluck('points')->map(fn ($p) => (float) $p)->sort()->values()->all());
        $this->assertSame(15.0, (float) $data['total_points']);

        $dibagi = $items->firstWhere('contributors', 2);
        $this->assertNotNull($dibagi, 'Task dengan 2 kontributor harus tercatat.');
        $this->assertSame(5.0, (float) $dibagi['points']);
    }
}
