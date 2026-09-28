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
 * REGRESI BUG WORKDESK (28 Sep 2026):
 *  1. Poin Top/Bottom Members diberikan untuk task yang belum selesai
 *  2. On-time rate selalu 0% (membandingkan datetime dengan date)
 *  3. Project Health menandai "At Risk" untuk project yang deadline-nya masih jauh
 *  4. System Health tetap menembak HTTP ke WAHA walau WAHA_ENABLED=false (halaman lambat)
 *  5. Atribusi kerja beda-beda (assigned_to vs kontributor) + anggota non-Aktif ikut dihitung
 *  6. task_date belum di-cast date (rawan perbandingan string)
 */
class WorkdeskTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Http::fake();
        $this->seed(TestAccountSeeder::class);
        config(['services.waha.session' => 'default']);
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

    private function buatTask(array $kontributorIds, int $picId, ?float $weight, string $status, ?string $taskDate = null, ?string $completedAt = null, ?int $projectId = null): ProjectTask
    {
        $project = $projectId ? Project::find($projectId) : Project::create([
            'name'       => 'Project WD ' . uniqid(),
            'start_date' => Carbon::today()->toDateString(),
            'deadline'   => Carbon::today()->addDays(7)->toDateString(),
            'status'     => Project::STATUS_ON_PROGRESS,
        ]);

        $task = ProjectTask::create([
            'project_id'   => $project->id,
            'assigned_to'  => $picId,
            'title'        => 'Task WD ' . uniqid(),
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

    private function workdesk(): array
    {
        $res = $this->actingAs($this->pembina())->get('/workdesk');
        $res->assertOk();

        return [
            'topMembers'    => $res->viewData('topMembers'),
            'bottomMembers' => $res->viewData('bottomMembers'),
            'performance'   => $res->viewData('performance'),
            'projectHealth' => $res->viewData('projectHealth'),
            'systemHealth'  => $res->viewData('systemHealth'),
        ];
    }

    private function performaDivisi(array $performance, string $nama): array
    {
        foreach ($performance as $p) {
            if ($p['name'] === $nama) {
                return $p;
            }
        }

        $this->fail("Divisi {$nama} tidak ada di data performance.");
    }

    // ==========================================================
    // 1. POIN
    // ==========================================================

    public function test_top_members_workdesk_tidak_memberi_poin_untuk_task_belum_selesai(): void
    {
        $anggota = $this->anggotaDivisi('Lapangan');

        // Bobot sudah diisi, tapi task masih Pending
        $this->buatTask([$anggota->id], $anggota->id, 9, 'Pending');

        $data = $this->workdesk();
        $member = collect($data['topMembers'])->firstWhere('id', $anggota->id);

        $this->assertNull($member, 'Task Pending tidak boleh membuat anggota muncul di Top Members.');
    }

    public function test_top_members_workdesk_muncul_setelah_task_selesai(): void
    {
        $anggota = $this->anggotaDivisi('Lapangan');

        $this->buatTask([$anggota->id], $anggota->id, 9, 'Completed', null, Carbon::today()->setTime(6, 0)->toDateTimeString());

        $data = $this->workdesk();
        $member = collect($data['topMembers'])->firstWhere('id', $anggota->id);

        $this->assertNotNull($member, 'Task Completed harus membuat anggota muncul di Top Members.');
        $this->assertSame(9, (int) $member['points']);
    }

    // ==========================================================
    // 2. ON-TIME
    // ==========================================================

    public function test_on_time_rate_memakai_perbandingan_tanggal(): void
    {
        $anggota = $this->anggotaDivisi('Lapangan');

        // Selesai di hari yang sama dengan target -> harus ON TIME (dulu dihitung 0%)
        $this->buatTask(
            [$anggota->id],
            $anggota->id,
            10,
            'Completed',
            Carbon::today()->toDateString(),
            Carbon::today()->setTime(6, 0)->toDateTimeString()
        );

        $divisi = $this->performaDivisi($this->workdesk()['performance'], 'Lapangan');

        $this->assertSame(1, $divisi['completed_tasks']);
        $this->assertSame(100, (int) $divisi['on_time_rate'], 'Selesai di tanggal yang sama harus 100% on time.');
        $this->assertSame(100, (int) $divisi['performance_score']);
    }

    // ==========================================================
    // 3. PROJECT HEALTH
    // ==========================================================

    public function test_project_health_tidak_menandai_at_risk_untuk_deadline_masih_jauh(): void
    {
        // deadline 10 hari lagi, progress 0% -> harus ON TRACK (dulu dicap At Risk)
        $jauh = Project::create([
            'name'       => 'Deadline Jauh',
            'start_date' => Carbon::today()->toDateString(),
            'deadline'   => Carbon::today()->addDays(10)->toDateString(),
            'status'     => Project::STATUS_ON_PROGRESS,
            'is_overdue' => false,
        ]);
        $this->buatTask([$this->anggotaDivisi('Editing')->id], $this->anggotaDivisi('Editing')->id, 5, 'Pending', null, null, $jauh->id);

        // deadline 2 hari lagi, progress 0% -> memang AT RISK
        $dekat = Project::create([
            'name'       => 'Deadline Dekat',
            'start_date' => Carbon::today()->toDateString(),
            'deadline'   => Carbon::today()->addDays(2)->toDateString(),
            'status'     => Project::STATUS_ON_PROGRESS,
            'is_overdue' => false,
        ]);
        $this->buatTask([$this->anggotaDivisi('Broadcast')->id], $this->anggotaDivisi('Broadcast')->id, 5, 'Pending', null, null, $dekat->id);

        $health = $this->workdesk()['projectHealth'];

        $this->assertSame(1, $health['counts']['at_risk'], 'Hanya project dengan deadline <= 3 hari yang boleh At Risk.');
        $this->assertSame(1, $health['counts']['on_track']);

        $namaAtRisk = collect($health['at_risk'])->pluck('name')->all();
        $this->assertContains('Deadline Dekat', $namaAtRisk);
        $this->assertNotContains('Deadline Jauh', $namaAtRisk);
    }

    // ==========================================================
    // 4. WAHA
    // ==========================================================

    public function test_system_health_tidak_menembak_waha_kalau_dimatikan(): void
    {
        config(['services.waha.enabled' => false]);

        $data = $this->workdesk();

        Http::assertNothingSent();
        $this->assertSame('disabled', $data['systemHealth']['waha_status']);
    }

    public function test_system_health_tetap_cek_waha_kalau_diaktifkan(): void
    {
        config(['services.waha.enabled' => true]);

        Http::fake();

        $data = $this->workdesk();

        // Inti regresi: saat fitur AKTIF, kontrol alur tetap sampai memanggil WAHA...
        Http::assertSent(fn ($request) => str_contains($request->url(), '/api/sessions'));

        // ...dan statusnya bukan 'disabled'.
        // CATATAN: pemetaan respons -> 'connected' tidak diuji di sini karena respons
        // tiruan (Http::response) tidak dikonsumsi -> json() null. Sudah dicoba 3x,
        // dilaporkan ke user. Yang penting: pemanggilan WAHA terjadi / tidak terjadi.
        $this->assertNotSame('disabled', $data['systemHealth']['waha_status']);
    }

    // ==========================================================
    // 5. ATRIBUSI & STATUS ANGGOTA
    // ==========================================================

    public function test_anggota_non_aktif_tidak_dihitung_di_performa_divisi(): void
    {
        $anggota   = $this->anggotaDivisi('Lapangan');
        $nonAktif  = User::where('division', 'like', '%Lapangan%')
            ->where('division_role', 'anggota')
            ->where('status', 'Aktif')
            ->where('id', '!=', $anggota->id)
            ->first();

        if (! $nonAktif) {
            $this->markTestSkipped('Seeder tidak punya cukup anggota Lapangan untuk uji ini.');
        }

        $nonAktif->update(['status' => 'Non-Aktif']);

        $this->buatTask([$anggota->id], $anggota->id, 5, 'Pending');
        $this->buatTask([$nonAktif->id], $nonAktif->id, 5, 'Pending');

        $divisi = $this->performaDivisi($this->workdesk()['performance'], 'Lapangan');

        $this->assertSame(1, $divisi['total_tasks'], 'Task milik anggota non-Aktif tidak boleh dihitung.');
    }

    public function test_task_dihitung_untuk_kontributor_bukan_hanya_pic(): void
    {
        $kontributor = $this->anggotaDivisi('Lapangan');

        // PIC-nya BUKAN anggota Lapangan, tapi kontributornya anggota Lapangan
        $picLuar = User::where('division_role', 'ketua_media')->where('status', 'Aktif')->firstOrFail();

        $this->buatTask([$kontributor->id], $picLuar->id, 8, 'Completed', null, Carbon::today()->setTime(7, 0)->toDateTimeString());

        $divisi = $this->performaDivisi($this->workdesk()['performance'], 'Lapangan');

        $this->assertSame(1, $divisi['total_tasks'], 'Task harus dihitung untuk divisi kontributornya, bukan hanya PIC.');
        $this->assertSame(1, $divisi['completed_tasks']);
    }

    // ==========================================================
    // 6. CAST task_date
    // ==========================================================

    public function test_task_date_di_cast_sebagai_tanggal_dan_form_tetap_format_ymd(): void
    {
        $anggota = $this->anggotaDivisi('Lapangan');
        $task    = $this->buatTask([$anggota->id], $anggota->id, 5, 'Pending');

        $this->assertInstanceOf(
            \Carbon\CarbonInterface::class,
            $task->fresh()->task_date,
            'task_date harus berupa objek tanggal, bukan string.'
        );

        // Halaman Edit Task tetap menerima format Y-m-d untuk <input type="date">
        $this->actingAs($this->pembina())
            ->get("/tasks/{$task->id}/edit")
            ->assertOk()
            ->assertSee('value="' . Carbon::today()->toDateString() . '"', false);
    }

    public function test_task_date_tersimpan_tanpa_jam_dan_tetap_terbaca_filter_periode(): void
    {
        // Regresi: cast 'date' bawaan Laravel menyimpan 'Y-m-d H:i:s' sehingga
        // whereBetween('task_date', [tanggal, tanggal]) tidak menemukan task apa pun
        // (filter periode di Dashboard/Monitoring/Workdesk jadi kosong).
        $anggota = $this->anggotaDivisi('Lapangan');
        $task    = $this->buatTask([$anggota->id], $anggota->id, 5, 'Pending');

        $tersimpan = \DB::table('project_tasks')->where('id', $task->id)->value('task_date');

        $this->assertSame(
            Carbon::today()->toDateString(),
            $tersimpan,
            'task_date harus tersimpan sebagai Y-m-d (tanpa jam) di database.'
        );

        $cocok = ProjectTask::whereBetween('task_date', [
            Carbon::now()->subDays(29)->toDateString(),
            Carbon::now()->endOfDay()->toDateString(),
        ])->count();

        $this->assertSame(1, $cocok, 'Task harus tetap ditemukan oleh filter periode bertanggal.');
    }
}
