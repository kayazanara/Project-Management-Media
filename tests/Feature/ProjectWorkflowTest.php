<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\ProjectTask;
use App\Models\User;
use App\Services\ProjectWorkflowService;
use Carbon\Carbon;
use Database\Seeders\TestAccountSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Test alur workflow project: Planning -> Task Review -> Scoring -> On Progress
 * -> Review -> Upload, plus guard/aturan izin per role.
 */
class ProjectWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected ProjectWorkflowService $workflow;

    protected function setUp(): void
    {
        parent::setUp();

        Http::fake(); // notifikasi WhatsApp jangan keluar sungguhan
        $this->seed(TestAccountSeeder::class);
        $this->workflow = app(ProjectWorkflowService::class);
    }

    protected function user(string $role): User
    {
        return User::where('division_role', $role)->where('status', 'Aktif')->firstOrFail();
    }

    protected function buatProject(string $status, ?string $deadline = null): Project
    {
        return Project::create([
            'name'       => 'Project Uji ' . $status,
            'start_date' => Carbon::today()->toDateString(),
            'deadline'   => $deadline ?? Carbon::today()->addDays(7)->toDateString(),
            'status'     => $status,
        ]);
    }

    // ==========================================================
    // LOGIKA SERVICE
    // ==========================================================

    public function test_rilis_hanya_boleh_kalau_semua_task_sudah_discoring_dan_diapprove(): void
    {
        $project = $this->buatProject(Project::STATUS_SCORING);

        $this->assertFalse($this->workflow->isReadyToRelease($project), 'Tanpa task seharusnya belum siap rilis.');
        $this->assertFalse($this->workflow->releaseReadiness($project)['ready']);

        $task = ProjectTask::create([
            'project_id' => $project->id,
            'title'      => 'Task A',
            'status'     => 'Pending',
        ]);

        $this->assertFalse($this->workflow->isReadyToRelease($project->fresh()), 'Task belum discoring.');

        $task->update(['scored_at' => now()]);
        $this->assertFalse($this->workflow->isReadyToRelease($project->fresh()), 'Sudah discoring tapi belum di-approve.');

        $task->update(['is_approved' => true]);
        $this->assertTrue($this->workflow->isReadyToRelease($project->fresh()), 'Sudah discoring + approve, harusnya siap rilis.');
    }

    public function test_progress_project_dihitung_dari_task_completed(): void
    {
        $project = $this->buatProject(Project::STATUS_ON_PROGRESS);

        $this->assertSame(0, $this->workflow->progress($project)['percent']);

        foreach (['Completed', 'Completed', 'Pending'] as $i => $status) {
            ProjectTask::create([
                'project_id' => $project->id,
                'title'      => 'Task ' . ($i + 1),
                'status'     => $status,
            ]);
        }

        $progress = $this->workflow->progress($project->fresh());

        $this->assertSame(3, $progress['total']);
        $this->assertSame(2, $progress['completed']);
        $this->assertSame(67, $progress['percent']); // 2/3 = 66.67 -> 67
    }

    public function test_sync_overdue_menandai_hanya_project_lewat_deadline_yang_belum_completed(): void
    {
        $lewat  = $this->buatProject(Project::STATUS_ON_PROGRESS, Carbon::today()->subDays(2)->toDateString());
        $aman   = $this->buatProject(Project::STATUS_ON_PROGRESS, Carbon::today()->addDays(2)->toDateString());
        $selesai = $this->buatProject(Project::STATUS_COMPLETED, Carbon::today()->subDays(2)->toDateString());

        $marked = $this->workflow->syncOverdueFlags();

        $this->assertSame(1, $marked, 'Hanya 1 project yang seharusnya ditandai overdue.');
        $this->assertTrue((bool) $lewat->fresh()->is_overdue);
        $this->assertFalse((bool) $aman->fresh()->is_overdue);
        $this->assertFalse((bool) $selesai->fresh()->is_overdue, 'Project Completed tidak boleh overdue.');
    }

    public function test_aturan_ubah_status_per_role(): void
    {
        $project = $this->buatProject(Project::STATUS_ON_PROGRESS);

        $this->assertTrue($this->workflow->canUserChangeStatus($project, $this->user('pembina'), Project::STATUS_REVIEW));
        $this->assertTrue($this->workflow->canUserChangeStatus($project, $this->user('ketua_media'), Project::STATUS_REVIEW));
        $this->assertTrue($this->workflow->canUserChangeStatus($project, $this->user('kepala_divisi'), Project::STATUS_REVIEW));
        $this->assertFalse($this->workflow->canUserChangeStatus($project, $this->user('anggota'), Project::STATUS_REVIEW), 'Anggota tidak boleh ubah status.');

        // Kepala divisi tidak boleh lompat langsung ke Completed (transisi tidak valid)
        $this->assertFalse($this->workflow->canUserChangeStatus($project, $this->user('kepala_divisi'), Project::STATUS_COMPLETED));
    }

    // ==========================================================
    // ALUR LENGKAP VIA HTTP
    // ==========================================================

    public function test_alur_lengkap_planning_sampai_upload(): void
    {
        $pembina = $this->user('pembina');
        $ketua   = $this->user('ketua_media');
        $kepala  = $this->user('kepala_divisi');

        // 1) Pembina membuat project
        $this->actingAs($pembina)->post('/projects', [
            'name'       => 'Project Alur Uji',
            'user_ids'   => [$kepala->id],
            'start_date' => Carbon::today()->toDateString(),
            'deadline'   => Carbon::today()->addDays(7)->toDateString(),
            'brief'      => 'Brief pengujian alur.',
        ])->assertRedirect();

        $project = Project::where('name', 'Project Alur Uji')->firstOrFail();
        $this->assertSame(Project::STATUS_PLANNING, $project->status);
        $this->assertTrue($project->users->contains($kepala->id), 'Anggota project harus ter-attach.');

        // 2) Ketua Media menyusun task
        $this->actingAs($ketua)->post("/projects/{$project->id}/tasks", [
            'title'       => 'Task Pertama',
            'assigned_to' => $kepala->id,
            'task_date'   => Carbon::today()->toDateString(),
        ])->assertRedirect();

        $task = $project->tasks()->firstOrFail();

        // 3) Planning -> Task Review (ketua media)
        $this->actingAs($ketua)->patch("/projects/{$project->id}/submit-task-review")->assertRedirect();
        $this->assertSame(Project::STATUS_TASK_REVIEW, $project->fresh()->status);

        // 4) Task Review -> Scoring (pembina, sekaligus menyimpan scoring task)
        $this->actingAs($pembina)->patch("/projects/{$project->id}/submit-scoring", [
            'tasks' => [['id' => $task->id, 'weight' => 100, 'is_approved' => 1]],
        ])->assertRedirect();

        $this->assertSame(Project::STATUS_SCORING, $project->fresh()->status);
        $this->assertNotNull($task->fresh()->scored_at);
        $this->assertTrue((bool) $task->fresh()->is_approved);

        // 5) Scoring -> On Progress (rilis oleh pembina)
        $this->actingAs($pembina)->patch("/projects/{$project->id}/release")->assertRedirect();
        $this->assertSame(Project::STATUS_ON_PROGRESS, $project->fresh()->status);
        $this->assertNotNull($project->fresh()->released_at);

        // 6) On Progress -> Review (kepala divisi, catatan minimal 10 karakter)
        $this->actingAs($kepala)->patch("/projects/{$project->id}/request-review", [
            'note' => 'Semua task sudah selesai dikerjakan.',
        ])->assertRedirect();
        $this->assertSame(Project::STATUS_REVIEW, $project->fresh()->status);

        // 7) Approve wajib DUA pihak — setelah pembina saja, status belum naik
        $this->actingAs($pembina)->patch("/projects/{$project->id}/approve")->assertRedirect();
        $this->assertNotNull($project->fresh()->approved_by_pembina_at);
        $this->assertSame(Project::STATUS_REVIEW, $project->fresh()->status, 'Belum boleh naik sebelum ketua media approve.');

        $this->actingAs($ketua)->patch("/projects/{$project->id}/approve")->assertRedirect();
        $this->assertNotNull($project->fresh()->approved_by_ketua_at);
        $this->assertSame(Project::STATUS_UPLOAD, $project->fresh()->status);
    }

    // ==========================================================
    // GUARD / PENOLAKAN
    // ==========================================================

    public function test_rilis_ditolak_kalau_task_belum_discoring(): void
    {
        $project = $this->buatProject(Project::STATUS_SCORING);

        ProjectTask::create([
            'project_id' => $project->id,
            'title'      => 'Task belum discoring',
            'status'     => 'Pending',
        ]);

        $this->actingAs($this->user('pembina'))
            ->patch("/projects/{$project->id}/release")
            ->assertRedirect();

        $this->assertSame(Project::STATUS_SCORING, $project->fresh()->status, 'Rilis harus ditolak, status tidak boleh berubah.');
    }

    public function test_mark_complete_ditolak_kalau_belum_ada_konten(): void
    {
        $project = $this->buatProject(Project::STATUS_UPLOAD);

        $this->actingAs($this->user('pembina'))
            ->patch("/projects/{$project->id}/mark-complete")
            ->assertRedirect(route('contents.formActivity', ['project_id' => $project->id]));

        $this->assertSame(Project::STATUS_UPLOAD, $project->fresh()->status);
    }

    public function test_anggota_tidak_bisa_ajukan_review(): void
    {
        $project = $this->buatProject(Project::STATUS_ON_PROGRESS);

        $this->actingAs($this->user('anggota'))
            ->patch("/projects/{$project->id}/request-review", [
                'note' => 'Coba ajukan review sebagai anggota.',
            ])->assertRedirect();

        $this->assertSame(Project::STATUS_ON_PROGRESS, $project->fresh()->status);
    }
}
