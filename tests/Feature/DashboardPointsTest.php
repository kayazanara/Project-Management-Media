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
 * REGRESI BUG POIN (28 Sep 2026):
 * Poin pernah diberikan untuk SEMUA task yang sudah punya bobot, termasuk task
 * yang masih Pending / In Progress — jadi anggota dapat poin tanpa menyelesaikan task.
 *
 * Aturan yang benar (dan diuji di sini):
 *   - poin HANYA dari task berstatus 'Completed'
 *   - poin dibagi RATA ke semua kontributor (weight 9 / 3 orang = 3 poin masing-masing)
 */
class DashboardPointsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Http::fake(); // notifikasi WhatsApp jangan keluar sungguhan
        $this->seed(TestAccountSeeder::class);
    }

    private function user(string $role): User
    {
        return User::where('division_role', $role)->where('status', 'Aktif')->firstOrFail();
    }

    /**
     * Buat task + daftar kontributornya.
     */
    private function buatTask(array $kontributorIds, float $weight, string $status, int $picId, ?string $taskDate = null): ProjectTask
    {
        $project = Project::create([
            'name'       => 'Project Poin ' . $status . ' ' . uniqid(),
            'start_date' => Carbon::today()->toDateString(),
            'deadline'   => Carbon::today()->addDays(7)->toDateString(),
            'status'     => Project::STATUS_ON_PROGRESS,
        ]);

        $task = ProjectTask::create([
            'project_id'   => $project->id,
            'assigned_to'  => $picId,
            'title'        => 'Task ' . $status . ' ' . uniqid(),
            'task_date'    => $taskDate ?? Carbon::today()->toDateString(),
            'status'       => $status,
            'weight'       => $weight,
            'is_approved'  => true,
            'scored_at'    => now(),
            'completed_at' => $status === 'Completed' ? now() : null,
        ]);

        $task->contributors()->attach($kontributorIds);

        return $task;
    }

    /**
     * Poin peringkat anggota di dashboard untuk 1 user (0 kalau tidak muncul).
     */
    private function poinDashboard(User $penglihat, int $userId): int
    {
        $res = $this->actingAs($penglihat)->get('/dashboard');
        $res->assertOk();

        $member = $res->viewData('topMembers')->firstWhere('id', $userId);

        return (int) ($member->points ?? 0);
    }

    // ==========================================================
    // DASHBOARD
    // ==========================================================

    public function test_task_belum_selesai_tidak_memberi_poin(): void
    {
        $penglihat = $this->user('pembina');
        $kepala    = $this->user('kepala_divisi');
        $anggota   = $this->user('anggota');

        // Bobot sudah diisi Pembina, tapi task BELUM dikerjakan
        $this->buatTask([$kepala->id, $anggota->id], 9, 'Pending', $kepala->id);

        $this->assertSame(0, $this->poinDashboard($penglihat, $kepala->id), 'Task Pending tidak boleh memberi poin.');
        $this->assertSame(0, $this->poinDashboard($penglihat, $anggota->id), 'Task Pending tidak boleh memberi poin.');
    }

    public function test_task_in_progress_juga_belum_memberi_poin(): void
    {
        $penglihat = $this->user('pembina');
        $kepala    = $this->user('kepala_divisi');

        $this->buatTask([$kepala->id], 8, 'In Progress', $kepala->id);

        $this->assertSame(0, $this->poinDashboard($penglihat, $kepala->id), 'Task In Progress tidak boleh memberi poin.');
    }

    public function test_task_selesai_membagi_poin_rata_ke_semua_kontributor(): void
    {
        $penglihat = $this->user('pembina');
        $ketua     = $this->user('ketua_media');
        $kepala    = $this->user('kepala_divisi');
        $anggota   = $this->user('anggota');

        // weight 9 dibagi 3 orang = 3 poin masing-masing
        $this->buatTask([$ketua->id, $kepala->id, $anggota->id], 9, 'Completed', $kepala->id);

        $this->assertSame(3, $this->poinDashboard($penglihat, $ketua->id));
        $this->assertSame(3, $this->poinDashboard($penglihat, $kepala->id));
        $this->assertSame(3, $this->poinDashboard($penglihat, $anggota->id));
    }

    public function test_campuran_hanya_task_selesai_yang_dihitung(): void
    {
        $penglihat = $this->user('pembina');
        $kepala    = $this->user('kepala_divisi');
        $anggota   = $this->user('anggota');

        // Selesai: weight 10 / 2 orang = 5 poin
        $this->buatTask([$kepala->id, $anggota->id], 10, 'Completed', $kepala->id);
        // Belum selesai: weight 20 / 2 orang = 10 poin (HARUS diabaikan)
        $this->buatTask([$kepala->id, $anggota->id], 20, 'In Progress', $kepala->id);

        $this->assertSame(5, $this->poinDashboard($penglihat, $kepala->id), 'Hanya task selesai yang boleh dihitung.');
        $this->assertSame(5, $this->poinDashboard($penglihat, $anggota->id), 'Hanya task selesai yang boleh dihitung.');
    }

    public function test_tanpa_task_selesai_tidak_muncul_di_peringkat(): void
    {
        $penglihat = $this->user('pembina');
        $kepala    = $this->user('kepala_divisi');

        $this->buatTask([$kepala->id], 7, 'Pending', $kepala->id);

        $res = $this->actingAs($penglihat)->get('/dashboard');
        $res->assertOk();

        $this->assertNull(
            $res->viewData('topMembers')->firstWhere('id', $kepala->id),
            'Tanpa task selesai, anggota tidak boleh masuk peringkat.'
        );
    }

    public function test_task_di_hari_pertama_periode_tetap_dihitung(): void
    {
        // Regresi bug batas periode: kolom task_date itu DATE, tapi dulu dibandingkan
        // dengan datetime '...00:00:00', sehingga task bertanggal TEPAT di hari pertama
        // periode ikut terbuang dari perhitungan poin.
        $penglihat = $this->user('pembina');
        $kepala    = $this->user('kepala_divisi');

        $hariPertama = Carbon::now()->startOfWeek()->toDateString();

        $this->buatTask([$kepala->id], 6, 'Completed', $kepala->id, $hariPertama);

        $this->assertSame(6, $this->poinDashboard($penglihat, $kepala->id), 'Task di hari pertama periode harus tetap dihitung.');
    }

    // ==========================================================
    // MONITORING (4 titik perhitungan poin)
    // ==========================================================

    public function test_poin_di_monitoring_juga_hanya_dari_task_selesai(): void
    {
        $pembina = $this->user('pembina');
        $kepala  = $this->user('kepala_divisi');

        $this->buatTask([$kepala->id], 12, 'Completed', $kepala->id); // 12 poin
        $this->buatTask([$kepala->id], 30, 'Pending', $kepala->id);   // harus diabaikan
        $this->buatTask([$kepala->id], 25, 'In Progress', $kepala->id); // harus diabaikan

        $res = $this->actingAs($pembina)->get('/monitoring');
        $res->assertOk();

        // 1) Tabel performa anggota
        $member = collect($res->viewData('members'))->firstWhere('id', $kepala->id);
        $this->assertNotNull($member, 'Anggota harus muncul di tabel monitoring.');
        $this->assertSame(12, (int) $member['points'], 'Poin tabel monitoring harus 12 (hanya task selesai).');
        $this->assertSame(3, (int) $member['total_tasks'], 'Total task tetap dihitung semua (3 task).');

        // 2) Direktori tim
        $direktori = collect($res->viewData('directory'))->firstWhere('id', $kepala->id);
        $this->assertNotNull($direktori);
        $this->assertSame(12, (int) $direktori['points'], 'Poin direktori tim harus 12.');

        // 3) Tren bulanan per anggota (getUserMonthlyTrend)
        $this->assertSame(12, (int) array_sum($direktori['trend_data']), 'Tren poin per anggota harus 12.');

        // 4) Grafik tren bulanan semua anggota (buildMonthlyTrend)
        $dataset = collect($res->viewData('monthlyTrend')['datasets'])->firstWhere('label', $kepala->name);
        $this->assertNotNull($dataset, 'Dataset tren bulanan untuk anggota ini harus ada.');
        $this->assertSame(12, (int) array_sum($dataset['data']), 'Grafik tren bulanan harus 12.');
    }
}
