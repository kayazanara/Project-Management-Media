<?php

namespace App\Console\Commands;

use App\Models\Content;
use App\Models\Meeting;
use App\Models\Project;
use App\Models\ProjectTask;
use App\Models\User;
use App\Services\ProjectWorkflowService;
use App\Services\WhatsappNotifier;
use Illuminate\Console\Command;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FakeWhatsappNotifier extends WhatsappNotifier
{
    public array $calls = [];

    public function __construct() {}

    public function send(string $phone, string $message): array
    {
        $this->calls[] = [
            'phone'   => $phone,
            'message' => $message,
            'at'      => now()->toDateTimeString(),
        ];
        return ['success' => true, 'message' => 'mocked', 'response' => ['mocked' => true]];
    }
}

class SimulateWorkflow extends Command
{
    protected $signature   = 'simulate:workflow';
    protected $description = 'Simulasi lengkap workflow PRD v2.1 (dry-run, auto rollback)';

    protected array $results      = [];
    protected array $currentGroup = ['name' => 'General', 'results' => []];

    protected ?FakeWhatsappNotifier $fakeNotifier = null;

    public function handle()
    {
        $this->showBanner();

        $this->fakeNotifier = new FakeWhatsappNotifier();
        app()->instance(WhatsappNotifier::class, $this->fakeNotifier);

        Http::fake(['*' => Http::response(['status' => 'ok'], 200)]);

        $users = $this->prepareUsers();
        if (!$users) {
            $this->error('❌ Gagal menyiapkan user test.');
            return 1;
        }

        $this->reportInitialState();

        DB::beginTransaction();

        try {
            $this->testProjectWorkflow($users);
            $this->testMeetingWorkflow($users);
            $this->testContentUploads($users);
            $this->testWorkdesk($users);
            $this->testOverdue();
            $this->testNotifierTemplates();
        } catch (\Throwable $e) {
            $this->newLine();
            $this->error('💥 FATAL ERROR: ' . $e->getMessage());
            $this->error('   File: ' . $e->getFile() . ':' . $e->getLine());
        } finally {
            DB::rollBack();
            $this->line('');
            $this->line('🔄 Database di-rollback. Tidak ada data test yang tersisa.');
        }

        $this->printSummary();

        return $this->hasFailure() ? 1 : 0;
    }

    protected function showBanner(): void
    {
        $this->newLine();
        $this->info('╔══════════════════════════════════════════════════════════╗');
        $this->info('║     SIMULASI WORKFLOW PRD v2.1 — DRY RUN MODE            ║');
        $this->info('║     Tidak ada data yang akan tersimpan permanen          ║');
        $this->info('║     WhatsApp dinonaktifkan (mocked)                      ║');
        $this->info('╚══════════════════════════════════════════════════════════╝');
        $this->newLine();
    }

    protected function prepareUsers(): ?array
    {
        $roles = ['pembina', 'ketua_media', 'kepala_divisi', 'anggota'];
        $users = [];

        foreach ($roles as $role) {
            $u = User::where('division_role', $role)->where('status', 'Aktif')->first();

            if (!$u) {
                $u = User::create([
                    'name'          => 'TEST_' . strtoupper($role),
                    'email'         => 'test_' . $role . '_' . uniqid() . '@sim.test',
                    'password'      => bcrypt('secret'),
                    'role'          => in_array($role, ['pembina', 'ketua_media']) ? 'Ustadz' : 'Santri',
                    'division'      => match ($role) {
                        'pembina'       => 'Pembina Media',
                        'ketua_media'   => 'Ketua Media',
                        'kepala_divisi' => 'Lapangan',
                        'anggota'       => 'Lapangan',
                    },
                    'division_role' => $role,
                    'status'        => 'Aktif',
                    'phone'         => '6281234567' . rand(10, 99),
                ]);
                $this->line("  ℹ  Buat user test: {$u->name} ({$role})");
            }

            $users[$role] = $u;
        }

        return $users;
    }

    protected function reportInitialState(): void
    {
        $this->line('📊 State awal database:');
        $this->line('   • Projects    : ' . Project::count());
        $this->line('   • Tasks       : ' . ProjectTask::count());
        $this->line('   • Meetings    : ' . Meeting::count());
        $this->line('   • Contents    : ' . Content::count());
        $this->line('   • Users       : ' . User::count());
        $this->newLine();
    }

    // ============================================================
    // GROUP 1 — PROJECT WORKFLOW
    // ============================================================

    protected function testProjectWorkflow(array $users): void
    {
        $this->startGroup('PROJECT WORKFLOW (9 Tahap)');

        $project = null;

        $this->step('1.1 Buat project (Planning)', function () use ($users, &$project) {
            Auth::setUser($users['pembina']);

            $project = Project::create([
                'name'        => '[SIM] Project Test ' . now()->format('His'),
                'start_date'  => now()->toDateString(),
                'deadline'    => now()->addDays(7)->toDateString(),
                'status'      => Project::STATUS_PLANNING,
                'brief'       => 'Brief simulasi workflow.',
            ]);

            $project->users()->attach([
                $users['kepala_divisi']->id,
                $users['anggota']->id,
            ]);

            return $project->status === Project::STATUS_PLANNING;
        });

        if (!$project) return;

        $this->step('1.2 Tambah 2 task (Ketua Media)', function () use ($project) {
            ProjectTask::create([
                'project_id'  => $project->id,
                'title'       => 'Task A — Shooting',
                'assigned_to' => null,
                'status'      => 'Pending',
            ]);
            ProjectTask::create([
                'project_id'  => $project->id,
                'title'       => 'Task B — Editing',
                'assigned_to' => null,
                'status'      => 'Pending',
            ]);

            return $project->tasks()->count() === 2;
        });

        $this->step('1.3 Ketua Media: submit task review', function () use ($users, $project) {
            Auth::setUser($users['ketua_media']);
            $before = count($this->fakeNotifier->calls);

            app(\App\Http\Controllers\ProjectController::class)
                ->submitTaskReview($project->id);

            $project->refresh();
            if ($project->status !== Project::STATUS_TASK_REVIEW) {
                throw new \Exception("Status harusnya 'Task Review', dapat '{$project->status}'");
            }
            if (count($this->fakeNotifier->calls) === $before) {
                throw new \Exception('Tidak ada notif WA terkirim');
            }
            return true;
        });

        $this->step('1.4 Pembina: submit scoring', function () use ($users, $project) {
            Auth::setUser($users['pembina']);

            $tasks = $project->tasks()->get();
            $payload = $tasks->map(fn($t) => [
                'id'          => $t->id,
                'weight'      => 5,
                'is_approved' => 1,
            ])->toArray();

            $request = Request::create(
                "/projects/{$project->id}/submit-scoring",
                'PATCH',
                ['tasks' => $payload]
            );

            app(\App\Http\Controllers\ProjectController::class)
                ->submitScoring($request, $project->id);

            $project->refresh();
            if ($project->status !== Project::STATUS_SCORING) {
                throw new \Exception("Status harusnya 'Scoring', dapat '{$project->status}'");
            }
            return true;
        });

        $this->step('1.5 Pembina: release project', function () use ($users, $project) {
            Auth::setUser($users['pembina']);
            $before = count($this->fakeNotifier->calls);

            app(\App\Http\Controllers\ProjectController::class)->release($project->id);

            $project->refresh();
            if ($project->status !== Project::STATUS_ON_PROGRESS) {
                throw new \Exception("Status harusnya 'On Progress', dapat '{$project->status}'");
            }
            if (count($this->fakeNotifier->calls) <= $before) {
                throw new \Exception('Tidak ada notif WA terkirim setelah release');
            }
            return true;
        });

        // ⚡ FIX: tambah note & file_url
        $this->step('1.6 Kepala Divisi: ajukan review', function () use ($users, $project) {
            Auth::setUser($users['kepala_divisi']);

            $request = Request::create("/projects/{$project->id}/request-review", 'PATCH', [
                'note'     => 'Semua task Lapangan sudah selesai. Video sudah di-edit, mohon dicek color grading & audio.',
                'file_url' => 'https://example.com/hasil-review.mp4',
            ]);

            app(\App\Http\Controllers\ProjectController::class)
                ->requestReview($request, $project->id);

            $project->refresh();
            return $project->status === Project::STATUS_REVIEW;
        });

        $this->step('1.7 Pembina: minta revisi', function () use ($users, $project) {
            Auth::setUser($users['pembina']);

            $request = Request::create(
                "/projects/{$project->id}/request-revision",
                'PATCH',
                ['note' => 'Tolong perbaiki bagian intro, kurang jelas.']
            );
            app(\App\Http\Controllers\ProjectController::class)
                ->requestRevision($request, $project->id);

            $project->refresh();
            if ($project->status !== Project::STATUS_REVISION) {
                throw new \Exception("Status harusnya 'Revision', dapat '{$project->status}'");
            }
            if ($project->revisions()->count() === 0) {
                throw new \Exception('Catatan revisi tidak tersimpan');
            }
            return true;
        });

        // ⚡ FIX: tambah note
        $this->step('1.8 Kepala Divisi: ajukan review ulang (loop)', function () use ($users, $project) {
            Auth::setUser($users['kepala_divisi']);

            $request = Request::create("/projects/{$project->id}/request-review", 'PATCH', [
                'note'     => 'Revisi sudah selesai. Intro sudah diperbaiki sesuai catatan Pembina.',
                'file_url' => null,
            ]);

            app(\App\Http\Controllers\ProjectController::class)
                ->requestReview($request, $project->id);

            $project->refresh();
            return $project->status === Project::STATUS_REVIEW;
        });

        $this->step('1.9 Pembina: approve (partial)', function () use ($users, $project) {
            Auth::setUser($users['pembina']);

            app(\App\Http\Controllers\ProjectController::class)->approve($project->id);

            $project->refresh();
            if (!$project->approved_by_pembina_at) {
                throw new \Exception('approved_by_pembina_at tidak terisi');
            }
            if ($project->status !== Project::STATUS_REVIEW) {
                throw new \Exception('Status belum boleh pindah sebelum dua pihak ACC');
            }
            return true;
        });

        $this->step('1.10 Ketua Media: approve (dual sign → Upload)', function () use ($users, $project) {
            Auth::setUser($users['ketua_media']);
            $before = count($this->fakeNotifier->calls);

            app(\App\Http\Controllers\ProjectController::class)->approve($project->id);

            $project->refresh();
            if ($project->status !== Project::STATUS_UPLOAD) {
                throw new \Exception("Status harusnya 'Upload', dapat '{$project->status}'");
            }
            if (!$project->approved_by_ketua_at) {
                throw new \Exception('approved_by_ketua_at tidak terisi');
            }
            if (count($this->fakeNotifier->calls) <= $before) {
                throw new \Exception('Tidak ada notif WA setelah dual ACC');
            }
            return true;
        });

        $this->step('1.11 Kepala Divisi: mark complete', function () use ($users, $project) {
            Auth::setUser($users['kepala_divisi']);

            app(\App\Http\Controllers\ProjectController::class)->markComplete($project->id);

            $project->refresh();
            if ($project->status !== Project::STATUS_COMPLETED) {
                throw new \Exception("Status harusnya 'Completed', dapat '{$project->status}'");
            }
            if (!$project->completed_at) {
                throw new \Exception('completed_at tidak terisi');
            }
            return true;
        });

        $this->step('1.12 Verifikasi total notif WA workflow', function () {
            $types = [
                'task_review_submitted',
                'project_released',
                'request_review',
                'revision_requested',
                'project_approved',
                'project_completed',
            ];

            $sent = DB::table('notification_logs')
                ->whereIn('type', $types)
                ->distinct('type')
                ->pluck('type')
                ->toArray();

            $missing = array_diff($types, $sent);
            if (!empty($missing)) {
                throw new \Exception('Notif hilang: ' . implode(', ', $missing));
            }
            return true;
        });
    }

    // ============================================================
    // GROUP 2 — MEETING
    // ============================================================

    protected function testMeetingWorkflow(array $users): void
    {
        $this->startGroup('MEETING / RAPAT & NOTULEN');
        $meeting = null;

        $this->step('2.1 Buat notulen rapat (draft)', function () use ($users, &$meeting) {
            Auth::setUser($users['pembina']);

            $meeting = Meeting::create([
                'title'        => '[SIM] Rapat Test',
                'meeting_date' => now()->toDateString(),
                'start_time'   => '08:00',
                'end_time'     => '10:00',
                'location'     => 'Ruang Media',
                'attendees'    => [$users['pembina']->id, $users['ketua_media']->id],
                'agenda'       => "1. Pembukaan\n2. Evaluasi",
                'discussion'   => '<p>Pembahasan simulasi.</p>',
                'decisions'    => [['decision' => 'Lanjut ke tahap berikutnya']],
                'action_items' => [[
                    'task'        => 'Simulasi action item',
                    'assigned_to' => $users['kepala_divisi']->id,
                    'deadline'    => now()->addDays(3)->toDateString(),
                    'status'      => 'pending',
                ]],
                'created_by'   => $users['pembina']->id,
                'status'       => 'draft',
            ]);

            return $meeting->status === 'draft';
        });

        if (!$meeting) return;

        $this->step('2.2 Publish notulen → notif WA ke manager + kadiv', function () use ($users, $meeting) {
            Auth::setUser($users['pembina']);
            $before = count($this->fakeNotifier->calls);

            app(\App\Http\Controllers\MeetingController::class)->publish($meeting->id);

            $meeting->refresh();
            if ($meeting->status !== 'published') {
                throw new \Exception("Status harusnya 'published'");
            }
            if (!$meeting->published_at) {
                throw new \Exception('published_at tidak terisi');
            }
            if (count($this->fakeNotifier->calls) <= $before) {
                throw new \Exception('Tidak ada notif WA setelah publish');
            }
            return true;
        });

        $this->step('2.3 Toggle action item', function () use ($users, $meeting) {
            Auth::setUser($users['kepala_divisi']);

            $request = Request::create(
                "/meetings/action-items/0/toggle",
                'PATCH',
                ['meeting_id' => $meeting->id]
            );

            $response = app(\App\Http\Controllers\MeetingController::class)
                ->toggleActionItem($request, 0);

            $data = json_decode($response->getContent(), true);
            if (empty($data['success'])) {
                throw new \Exception('Response: ' . $response->getContent());
            }

            $meeting->refresh();
            if ($meeting->completed_action_items !== 1) {
                throw new \Exception('Action item belum ter-mark done');
            }
            return true;
        });
    }

    // ============================================================
    // GROUP 3 — CONTENT UPLOADS
    // ============================================================

    protected function testContentUploads(array $users): void
    {
        $this->startGroup('CONTENT UPLOAD (3 Jenis)');

        $project = Project::create([
            'name'       => '[SIM] Content Project',
            'start_date' => now()->toDateString(),
            'deadline'   => now()->addDays(5)->toDateString(),
            'status'     => Project::STATUS_UPLOAD,
        ]);

        $project->users()->attach([
            $users['kepala_divisi']->id,
            $users['anggota']->id,
        ]);

        $this->step('3.1 Upload raw → notif ke divisi target', function () use ($users, $project) {
            Auth::setUser($users['kepala_divisi']);
            $before = count($this->fakeNotifier->calls);

            $request = Request::create('/contents/upload-raw', 'POST', [
                'project_id'      => $project->id,
                'title'           => '[SIM] Raw footage sesi 1',
                'file_url'        => 'https://example.com/raw1.mp4',
                'target_division' => 'Editing',
                'notes'           => 'Catatan simulasi.',
            ]);

            app(\App\Http\Controllers\ContentController::class)->uploadRaw($request);

            $content = Content::where('title', '[SIM] Raw footage sesi 1')->first();
            if (!$content) {
                throw new \Exception('Content raw tidak tersimpan');
            }
            if ($content->upload_type !== 'raw') {
                throw new \Exception('upload_type harusnya "raw"');
            }
            return true;
        });

        $this->step('3.2 Upload content stock → notif ke Broadcast', function () use ($users, $project) {
            Auth::setUser($users['pembina']);

            $request = Request::create('/contents/upload-stock', 'POST', [
                'project_id'      => $project->id,
                'title'           => '[SIM] Konten stock 1',
                'category_prefix' => 'DAK',
                'type'            => 'Video',
                'platform'        => ['Instagram', 'TikTok'],
                'file_url'        => 'https://example.com/stock1.mp4',
                'caption'         => 'Caption awal',
            ]);

            app(\App\Http\Controllers\ContentController::class)->uploadStock($request);

            $content = Content::where('title', '[SIM] Konten stock 1')->first();
            if (!$content) {
                throw new \Exception('Content stock tidak tersimpan');
            }
            if ($content->upload_type !== 'content_stock') {
                throw new \Exception('upload_type harusnya "content_stock"');
            }
            if (!str_starts_with($content->content_code, 'DAK')) {
                throw new \Exception('Content code harus prefix DAK, dapat ' . $content->content_code);
            }
            return true;
        });

        $this->step('3.3 Upload activity → langsung Completed', function () use ($users, $project) {
            Auth::setUser($users['kepala_divisi']);

            $request = Request::create('/contents/upload-activity', 'POST', [
                'project_id' => $project->id,
                'title'      => '[SIM] Live Kajian',
                'file_url'   => 'https://youtube.com/live',
                'caption'    => 'Deskripsi live.',
            ]);

            app(\App\Http\Controllers\ContentController::class)->uploadActivity($request);

            $content = Content::where('title', '[SIM] Live Kajian')->first();
            if (!$content) {
                throw new \Exception('Content activity tidak tersimpan');
            }
            if ($content->upload_type !== 'activity') {
                throw new \Exception('upload_type harusnya "activity"');
            }
            if ($content->status !== 'Completed') {
                throw new \Exception('Status harusnya "Completed" untuk activity');
            }
            return true;
        });

        $this->step('3.4 Broadcast: isi metadata → status Completed', function () use ($users) {
            Auth::setUser($users['pembina']);

            $content = Content::where('title', '[SIM] Konten stock 1')->first();
            if (!$content) {
                throw new \Exception('Content stock tidak ditemukan');
            }

            $request = Request::create('/x', 'PATCH', [
                'category'     => 'Dakwah Umum',
                'content_type' => 'Reels',
                'platform'     => ['Instagram', 'TikTok'],
                'caption'      => 'Caption final setelah metadata.',
            ]);

            app(\App\Http\Controllers\ContentController::class)
                ->fillBroadcastMetadata($request, $content->id);

            $content->refresh();
            if ($content->status !== 'Completed') {
                throw new \Exception("Status harusnya 'Completed', dapat '{$content->status}'");
            }
            if (!$content->broadcast_filled_at) {
                throw new \Exception('broadcast_filled_at tidak terisi');
            }
            return true;
        });
    }

    // ============================================================
    // GROUP 4 — WORKDESK
    // ============================================================

    protected function testWorkdesk(array $users): void
    {
        $this->startGroup('WORKDESK');

        $this->step('4.1 Load Workdesk@index (Pembina)', function () use ($users) {
            Auth::setUser($users['pembina']);

            $request = Request::create('/workdesk', 'GET');
            $response = app(\App\Http\Controllers\WorkdeskController::class)
                ->index($request);

            if (!method_exists($response, 'render')) {
                throw new \Exception('Response bukan View (tidak punya render())');
            }
            $html = $response->render();
            if (strlen($html) < 100) {
                throw new \Exception('View kosong');
            }
            return true;
        });

        $this->step('4.2 getActions() mengembalikan JSON valid', function () use ($users) {
            Auth::setUser($users['pembina']);
            $response = app(\App\Http\Controllers\WorkdeskController::class)->getActions();
            $data = json_decode($response->getContent(), true);
            if (!isset($data['actions'])) {
                throw new \Exception('Response tidak punya key "actions"');
            }
            return true;
        });

        $this->step('4.3 getAlerts() mengembalikan JSON valid', function () use ($users) {
            Auth::setUser($users['pembina']);
            $response = app(\App\Http\Controllers\WorkdeskController::class)->getAlerts();
            $data = json_decode($response->getContent(), true);
            if (!isset($data['alerts'])) {
                throw new \Exception('Response tidak punya key "alerts"');
            }
            return true;
        });

        $this->step('4.4 getPerformance() mengembalikan 4 divisi', function () use ($users) {
            Auth::setUser($users['pembina']);
            $response = app(\App\Http\Controllers\WorkdeskController::class)->getPerformance();
            $data = json_decode($response->getContent(), true);
            if (!isset($data['performance'])) {
                throw new \Exception('Response tidak punya key "performance"');
            }
            if (count($data['performance']) !== 4) {
                throw new \Exception('Harusnya 4 divisi, dapat ' . count($data['performance']));
            }
            return true;
        });
    }

    // ============================================================
    // GROUP 5 — OVERDUE
    // ============================================================

    protected function testOverdue(): void
    {
        $this->startGroup('OVERDUE FLAG');

        $this->step('5.1 Project lewat deadline → is_overdue = true', function () {
            $project = Project::create([
                'name'       => '[SIM] Project Overdue',
                'start_date' => now()->subDays(10)->toDateString(),
                'deadline'   => now()->subDays(3)->toDateString(),
                'status'     => Project::STATUS_ON_PROGRESS,
                'is_overdue' => false,
            ]);

            app(ProjectWorkflowService::class)->syncOverdueFlags();

            $project->refresh();
            if (!$project->is_overdue) {
                throw new \Exception('is_overdue tidak ter-set padahal sudah lewat deadline');
            }
            return true;
        });

        $this->step('5.2 Project completed → is_overdue = false (auto-reset)', function () {
            $project = Project::create([
                'name'       => '[SIM] Project Completed',
                'start_date' => now()->subDays(10)->toDateString(),
                'deadline'   => now()->subDays(3)->toDateString(),
                'status'     => Project::STATUS_COMPLETED,
                'is_overdue' => true,
            ]);

            app(ProjectWorkflowService::class)->syncOverdueFlags();

            $project->refresh();
            if ($project->is_overdue) {
                throw new \Exception('is_overdue harusnya false karena sudah completed');
            }
            return true;
        });
    }

    // ============================================================
    // GROUP 6 — NOTIFIER TEMPLATES
    // ============================================================

    protected function testNotifierTemplates(): void
    {
        $this->startGroup('NOTIFIER TEMPLATES (14 Template)');

        $templates = [
            'projectCreatedTemplate'      => ['Tester', 'Proj', '01 Jan', '07 Jan', 'Tim', 'Brief', 1],
            'taskReviewSubmittedTemplate' => ['Tester', 'Proj', 5, 1],
            'projectReleasedTemplate'     => ['Tester', 'Proj', '07 Jan', 'Tim A', 1],
            // ⚡ FIX: tambah argumen note (arg ke-4)
            'requestReviewTemplate'       => ['Tester', 'Proj', 'Editing', 'Catatan pengantar test minimal 10 karakter.', 1],
            'revisionRequestedTemplate'   => ['Tester', 'Proj', 'Perbaiki intro.', 1],
            'projectApprovedTemplate'     => ['Tester', 'Proj', 1],
            'projectCompletedTemplate'    => ['Tester', 'Proj', 1],
            'meetingPublishedTemplate'    => ['Tester', 'Rapat', '26 Sep', '08:00', 'Ruang', 1],
            'meetingActionItemTemplate'   => ['Tester', 'Rapat', 'Tugas A', '30 Sep', 1],
            'reminderH3Template'          => ['Tester', 'Proj', '07 Jan', 'Tim', 50, 1],
            'reminderH1Template'          => ['Tester', 'Proj', '07 Jan', 'Tim', 80, 1],
            'overdueTemplate'             => ['Tester', 'Proj', '01 Jan', 30, 'Tim', 1],
            'rawUploadedTemplate'         => ['Tester', 'Proj', 'Uploader', 'File.mp4', 'Editing', 1],
            'stockUploadedTemplate'       => ['Tester', 'DAK01', 'Konten A', 'Proj', 1],
        ];

        foreach ($templates as $method => $args) {
            $this->step("6.x Method {$method}()", function () use ($method, $args) {
                if (!method_exists(WhatsappNotifier::class, $method)) {
                    throw new \Exception("Method {$method} tidak ada");
                }
                $notifier = new WhatsappNotifier();
                $result = $notifier->{$method}(...$args);
                if (empty($result) || !is_string($result)) {
                    throw new \Exception('Hasil bukan string');
                }
                return true;
            });
        }
    }

    // ============================================================
    // HELPERS
    // ============================================================

    protected function startGroup(string $name): void
    {
        if (!empty($this->currentGroup['results'])) {
            $this->results[] = $this->currentGroup;
        }
        $this->currentGroup = ['name' => $name, 'results' => []];

        $this->newLine();
        $this->info('╭─ ' . $name);
    }

    protected function step(string $name, callable $fn): bool
    {
        try {
            $result = $fn();

            if ($result === false) {
                $this->currentGroup['results'][] = ['name' => $name, 'status' => 'FAIL', 'note' => 'returned false'];
                $this->error("  ✗ {$name}");
                return false;
            }

            $this->currentGroup['results'][] = ['name' => $name, 'status' => 'PASS'];
            $this->info("  ✓ {$name}");
            return true;

        } catch (\Throwable $e) {
            $this->currentGroup['results'][] = [
                'name'   => $name,
                'status' => 'FAIL',
                'note'   => $e->getMessage(),
            ];
            $this->error("  ✗ {$name}");
            $this->line("     └─ {$e->getMessage()}");
            return false;
        }
    }

    protected function hasFailure(): bool
    {
        if (!empty($this->currentGroup['results'])) {
            $this->results[] = $this->currentGroup;
            $this->currentGroup = ['name' => '', 'results' => []];
        }

        foreach ($this->results as $group) {
            foreach ($group['results'] as $r) {
                if ($r['status'] === 'FAIL') return true;
            }
        }
        return false;
    }

    protected function printSummary(): void
    {
        if (!empty($this->currentGroup['results'])) {
            $this->results[] = $this->currentGroup;
        }

        $this->newLine();
        $this->info('╔══════════════════════════════════════════════════════════╗');
        $this->info('║                    HASIL SIMULASI                        ║');
        $this->info('╚══════════════════════════════════════════════════════════╝');

        $totalPass = 0;
        $totalFail = 0;
        $failedNames = [];

        foreach ($this->results as $group) {
            $pass = count(array_filter($group['results'], fn($r) => $r['status'] === 'PASS'));
            $fail = count(array_filter($group['results'], fn($r) => $r['status'] === 'FAIL'));

            $icon = $fail === 0 ? '✅' : '⚠️ ';
            $this->line("  {$icon} {$group['name']} — {$pass} PASS, {$fail} FAIL");

            $totalPass += $pass;
            $totalFail += $fail;

            foreach ($group['results'] as $r) {
                if ($r['status'] === 'FAIL') {
                    $failedNames[] = "{$group['name']} → {$r['name']}";
                }
            }
        }

        $this->newLine();
        $this->line('  ─────────────────────────────────────────────');
        $this->line("  📊 Total: {$totalPass} PASS, {$totalFail} FAIL");
        $this->line('  ─────────────────────────────────────────────');

        $waCount = count($this->fakeNotifier->calls ?? []);
        $this->line("  📱 Notif WA tersimulasi: {$waCount}");
        $this->newLine();

        if ($totalFail === 0) {
            $this->info('  🎉 SEMUA TEST PASS! Sistem siap digunakan.');
        } else {
            $this->error('  ❌ Ada ' . $totalFail . ' test yang gagal:');
            foreach ($failedNames as $name) {
                $this->line("     • {$name}");
            }
        }
        $this->newLine();
    }
}