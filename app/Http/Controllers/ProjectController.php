<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Project;
use App\Models\User;
use App\Models\ProjectTask;
use App\Models\ProjectRevision;
use App\Models\Content;
use App\Events\ProjectCreated;
use App\Services\ProjectWorkflowService;
use App\Services\WhatsappNotifier;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class ProjectController extends Controller
{
    protected ProjectWorkflowService $workflow;
    protected WhatsappNotifier $notifier;

    public function __construct(
        ProjectWorkflowService $workflow,
        WhatsappNotifier $notifier
    ) {
        $this->workflow = $workflow;
        $this->notifier = $notifier;
    }

    // ============================================================
    // HELPERS
    // ============================================================

    private function isManager($user): bool
    {
        return $user && $user->isManager();
    }

    private function isKepalaDivisi($user): bool
    {
        return $user && $user->isKepalaDivisi();
    }

    private function notifyUsers($users, string $type, ?int $projectId, callable $buildMessage): void
    {
        foreach ($users as $user) {
            if (empty($user->phone)) {
                continue;
            }
            try {
                $message = $buildMessage($this->notifier, $user);
                $this->notifier->sendAndLog($user, $projectId, $type, $message);
            } catch (\Throwable $e) {
                Log::warning("[Workflow Notif] Gagal kirim {$type} ke user {$user->id}: {$e->getMessage()}");
            }
        }
    }

    // ============================================================
    // INDEX
    // ============================================================

    public function index()
    {
        $this->workflow->syncOverdueFlags();

        $projects = Project::where('status', '!=', Project::STATUS_COMPLETED)
            ->with('users')
            ->withCount([
                'tasks as total_tasks_count',
                'tasks as completed_tasks_count' => function ($q) {
                    $q->where('status', 'Completed');
                },
            ])
            ->orderBy('deadline', 'asc')
            ->get();

        $completedProjects = Project::where('status', Project::STATUS_COMPLETED)
            ->with('users')
            ->withCount([
                'tasks as total_tasks_count',
                'tasks as completed_tasks_count' => function ($q) {
                    $q->where('status', 'Completed');
                },
            ])
            ->orderBy('completed_at', 'desc')
            ->get();

        $counts = [
            'planning'    => Project::where('status', Project::STATUS_PLANNING)->count(),
            'task_review' => Project::where('status', Project::STATUS_TASK_REVIEW)->count(),
            'scoring'     => Project::where('status', Project::STATUS_SCORING)->count(),
            'on_progress' => Project::where('status', Project::STATUS_ON_PROGRESS)->count(),
            'review'      => Project::where('status', Project::STATUS_REVIEW)->count(),
            'revision'    => Project::where('status', Project::STATUS_REVISION)->count(),
            'approved'    => Project::where('status', Project::STATUS_APPROVED)->count(),
            'upload'      => Project::where('status', Project::STATUS_UPLOAD)->count(),
            'overdue'     => Project::where('is_overdue', true)
                ->where('status', '!=', Project::STATUS_COMPLETED)
                ->count(),
        ];

        return view('projects.index', [
            'title' => 'Project Management',
            'projects' => $projects,
            'completedProjects' => $completedProjects,
            'counts' => $counts,
        ]);
    }

    // ============================================================
    // CREATE / STORE
    // ============================================================

    public function create()
    {
        $users = User::all();

        return view('projects.create', [
            'title' => 'Tambah Project Baru',
            'users' => $users,
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'user_ids' => 'required|array|min:1',
            'user_ids.*' => 'exists:users,id',
            'start_date' => 'required|date',
            'deadline' => 'required|date|after_or_equal:start_date',
            'reference_link' => 'nullable|string',
            'brief' => 'nullable|string',
        ], [
            'name.required' => 'Nama project wajib diisi.',
            'user_ids.required' => 'Minimal harus memilih satu orang yang terlibat dalam project.',
            'user_ids.min' => 'Minimal harus memilih satu orang yang terlibat dalam project.',
            'start_date.required' => 'Tanggal masuk project wajib diisi.',
            'deadline.required' => 'Tanggal deadline wajib diisi.',
            'deadline.after_or_equal' => 'Deadline tidak boleh lebih awal dari tanggal mulai project.',
        ]);

        $project = Project::create([
            'name' => $request->name,
            'start_date' => $request->start_date,
            'deadline' => $request->deadline,
            'status' => Project::STATUS_PLANNING,
            'reference_link' => $request->reference_link,
            'brief' => $request->brief,
        ]);

        $project->users()->attach($request->user_ids);

        ProjectCreated::dispatch($project);

        return redirect()->route('projects.show', $project->id)
            ->with('success', 'Project baru berhasil dibuat! Menunggu Ketua Media menyusun task.');
    }

    // ============================================================
    // EDIT / UPDATE / DESTROY
    // ============================================================

    public function edit($id)
    {
        $project = Project::with('users')->findOrFail($id);
        $users = User::all();

        return view('projects.edit', [
            'title' => 'Edit Project',
            'project' => $project,
            'users' => $users,
        ]);
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'user_ids' => 'required|array|min:1',
            'user_ids.*' => 'exists:users,id',
            'start_date' => 'required|date',
            'deadline' => 'required|date|after_or_equal:start_date',
            'reference_link' => 'nullable|string',
            'brief' => 'nullable|string',
        ]);

        $project = Project::findOrFail($id);

        $project->update([
            'name' => $request->name,
            'start_date' => $request->start_date,
            'deadline' => $request->deadline,
            'reference_link' => $request->reference_link,
            'brief' => $request->brief,
        ]);

        $project->users()->sync($request->user_ids);

        return redirect()->route('projects.show', $id)
            ->with('success', 'Project berhasil diupdate!');
    }

    public function destroy($id)
    {
        $project = Project::findOrFail($id);
        $project->users()->detach();
        $project->delete();

        return redirect()->route('projects.index')->with('success', 'Project berhasil dihapus!');
    }

    // ============================================================
    // SHOW
    // ============================================================

    public function show($id)
    {
        $project = Project::with([
            'users',
            'tasks.assignedUser',
            'tasks.contributors',
            'revisions.user',
        ])->findOrFail($id);

        if ($project->status !== Project::STATUS_COMPLETED
            && $project->deadline
            && $project->deadline->isPast()
            && !$project->is_overdue) {
            $project->update(['is_overdue' => true]);
            $project->refresh();
        }

        $totalTasks = $project->tasks->count();
        $completedTasks = $project->tasks->where('status', 'Completed')->count();
        $progress = $totalTasks > 0 ? round(($completedTasks / $totalTasks) * 100) : 0;

        $users = User::all();

        $availableActions = $this->workflow->getAvailableActions($project, Auth::user());

        $latestReviewRequest = $project->revisions()
            ->where('type', 'review_request')
            ->first();

        $latestRevision = $project->revisions()
            ->where('type', 'revision')
            ->first();

        // ⚡ Cek jumlah konten untuk guard di view
        $projectContentsCount = Content::where('project_id', $project->id)->count();

        return view('projects.show', [
            'title' => 'Detail Project: ' . $project->name,
            'project' => $project,
            'progress' => $progress,
            'users' => $users,
            'availableActions' => $availableActions,
            'latestReviewRequest' => $latestReviewRequest,
            'latestRevision' => $latestRevision,
            'projectContentsCount' => $projectContentsCount,
        ]);
    }

    // ============================================================
    // TASK CRUD
    // ============================================================

    public function storeTask(Request $request, $id)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'assigned_to' => 'nullable|exists:users,id',
            'task_date' => 'nullable|date',
            'description' => 'nullable|string',
            'division' => 'nullable|string',
        ]);

        ProjectTask::create([
            'project_id' => $id,
            'assigned_to' => $request->assigned_to,
            'title' => $request->title,
            'description' => $request->description,
            'task_date' => $request->task_date,
            'status' => 'Pending',
        ]);

        return redirect()->route('projects.show', $id)->with('success', 'Task baru berhasil ditambahkan!');
    }

    public function toggleTask(Request $request, $id)
    {
        $task = ProjectTask::findOrFail($id);

        if ($task->status == 'Completed') {
            $task->status = 'Pending';
            $task->completed_at = null;
            $task->save();

            return redirect()->back()->with('success', 'Task dibuka kembali. Kontributor tetap tersimpan.');
        }

        $request->validate([
            'contributors' => 'required|array|min:1',
            'contributors.*' => 'exists:users,id',
        ], [
            'contributors.required' => 'Pilih minimal 1 kontributor untuk task ini.',
            'contributors.min' => 'Pilih minimal 1 kontributor untuk task ini.',
        ]);

        $task->status = 'Completed';
        $task->completed_at = now();
        $task->save();

        $task->contributors()->sync($request->contributors);

        $names = User::whereIn('id', $request->contributors)->pluck('name')->implode(', ');

        return redirect()->back()->with('success', "Task selesai! Kontributor: {$names}");
    }

    public function destroyTask($id)
    {
        $task = ProjectTask::findOrFail($id);
        $projectId = $task->project_id;
        $task->delete();

        return redirect()->route('projects.show', $projectId)->with('success', 'Task berhasil dihapus!');
    }

    public function editTask($id)
    {
        $task = ProjectTask::with('project.users')->findOrFail($id);

        return view('projects.edit-task', [
            'title' => 'Edit Task: ' . $task->title,
            'task' => $task,
            'project' => $task->project,
        ]);
    }

    public function updateTask(Request $request, $id)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'assigned_to' => 'nullable|exists:users,id',
            'task_date' => 'nullable|date',
            'description' => 'nullable|string',
        ]);

        $task = ProjectTask::findOrFail($id);
        $task->update([
            'title' => $request->title,
            'assigned_to' => $request->assigned_to,
            'task_date' => $request->task_date,
            'description' => $request->description,
        ]);

        return redirect()->route('projects.show', $task->project_id)->with('success', 'Task berhasil diperbarui!');
    }

    // ============================================================
    // WORKFLOW: 1. SUBMIT TASK REVIEW
    // ============================================================

    public function submitTaskReview($id)
    {
        $project = Project::findOrFail($id);
        $user = Auth::user();

        if (!$user->isKetuaMedia()) {
            return back()->with('error', 'Hanya Ketua Media yang bisa ajukan task review.');
        }

        if ($project->status !== Project::STATUS_PLANNING) {
            return back()->with('error', 'Project tidak dalam tahap Planning.');
        }

        $taskCount = $project->tasks()->count();
        if ($taskCount === 0) {
            return back()->with('error', 'Susun minimal 1 task sebelum ajukan ke Pembina.');
        }

        $project->update(['status' => Project::STATUS_TASK_REVIEW]);

        $pembinaUsers = User::where('division_role', 'pembina')
            ->where('status', 'Aktif')
            ->get();

        $this->notifyUsers(
            $pembinaUsers,
            'task_review_submitted',
            $project->id,
            fn($notifier, $u) => $notifier->taskReviewSubmittedTemplate(
                $u->name,
                $project->name,
                $taskCount,
                $project->id
            )
        );

        return redirect()->route('projects.show', $id)
            ->with('success', "Task berhasil diajukan ke Pembina Media ({$taskCount} task). Menunggu scoring.");
    }

    // ============================================================
    // WORKFLOW: 2. SUBMIT SCORING
    // ============================================================

    public function submitScoring(Request $request, $id)
    {
        $project = Project::findOrFail($id);
        $user = Auth::user();

        if (!$user->isPembina()) {
            return back()->with('error', 'Hanya Pembina Media yang bisa melakukan scoring.');
        }

        if ($project->status !== Project::STATUS_TASK_REVIEW) {
            return back()->with('error', 'Project tidak dalam tahap Task Review.');
        }

        $request->validate([
            'tasks' => 'required|array',
            'tasks.*.id' => 'required|exists:project_tasks,id',
            'tasks.*.weight' => 'required|integer|min:1',
            'tasks.*.is_approved' => 'nullable|boolean',
        ]);

        foreach ($request->tasks as $taskData) {
            $task = ProjectTask::where('id', $taskData['id'])
                ->where('project_id', $id)
                ->first();

            if ($task) {
                $task->update([
                    'weight' => $taskData['weight'],
                    'is_approved' => isset($taskData['is_approved']) && $taskData['is_approved'] ? true : false,
                    'scored_at' => now(),
                ]);
            }
        }

        $project->update([
            'status' => Project::STATUS_SCORING,
            'scored_at' => now(),
        ]);

        return redirect()->route('projects.show', $id)
            ->with('success', 'Scoring berhasil disimpan. Siap dirilis!');
    }

    // ============================================================
    // WORKFLOW: 3. RELEASE
    // ============================================================

    public function release($id)
    {
        $project = Project::findOrFail($id);
        $user = Auth::user();

        if (!$user->isPembina()) {
            return back()->with('error', 'Hanya Pembina Media yang bisa merilis project.');
        }

        if ($project->status !== Project::STATUS_SCORING) {
            return back()->with('error', 'Project tidak dalam tahap Scoring.');
        }

        if (!$this->workflow->isReadyToRelease($project)) {
            return back()->with('error', 'Semua task harus di-scoring & di-approve sebelum rilis.');
        }

        $project->update([
            'status' => Project::STATUS_ON_PROGRESS,
            'released_at' => now(),
        ]);

        $memberIds = $project->users()->pluck('users.id')->toArray();

        $recipients = User::where(function ($q) use ($memberIds) {
                $q->whereIn('id', $memberIds)
                  ->orWhere('division_role', 'kepala_divisi');
            })
            ->where('status', 'Aktif')
            ->get();

        $deadlineStr = $project->deadline
            ? $project->deadline->translatedFormat('d F Y')
            : '-';

        $listAnggota = $project->users()->pluck('name')->implode(', ');
        if (empty($listAnggota)) {
            $listAnggota = '-';
        }

        $this->notifyUsers(
            $recipients,
            'project_released',
            $project->id,
            fn($notifier, $u) => $notifier->projectReleasedTemplate(
                $u->name,
                $project->name,
                $deadlineStr,
                $listAnggota,
                $project->id
            )
        );

        return redirect()->route('projects.show', $id)
            ->with('success', 'Project berhasil dirilis! Tim sudah bisa mulai bekerja.');
    }

    // ============================================================
    // WORKFLOW: 4. REQUEST REVIEW
    // ============================================================

    public function requestReview(Request $request, $id)
    {
        $project = Project::findOrFail($id);
        $user = Auth::user();

        if (!$user->isKepalaDivisi()) {
            return back()->with('error', 'Hanya Kepala Divisi yang bisa ajukan review.');
        }

        $allowedStatuses = [Project::STATUS_ON_PROGRESS, Project::STATUS_REVISION];
        if (!in_array($project->status, $allowedStatuses, true)) {
            return back()->with('error', 'Project tidak dalam tahap On Progress atau Revision.');
        }

        $request->validate([
            'note'     => 'required|string|min:10',
            'file_url' => 'nullable|string|max:500',
        ], [
            'note.required' => 'Catatan pengantar wajib diisi.',
            'note.min'      => 'Catatan pengantar minimal 10 karakter.',
        ]);

        ProjectRevision::create([
            'project_id' => $project->id,
            'user_id'    => $user->id,
            'type'       => 'review_request',
            'note'       => $request->note,
            'file_url'   => $request->file_url,
        ]);

        $project->update(['status' => Project::STATUS_REVIEW]);

        $recipients = User::whereIn('division_role', ['pembina', 'ketua_media'])
            ->where('status', 'Aktif')
            ->get();

        $divisiName = $user->division ?? '-';

        $this->notifyUsers(
            $recipients,
            'request_review',
            $project->id,
            fn($notifier, $u) => $notifier->requestReviewTemplate(
                $u->name,
                $project->name,
                $divisiName,
                $request->note,
                $project->id
            )
        );

        return redirect()->route('projects.show', $id)
            ->with('success', 'Project diajukan untuk review. Menunggu persetujuan Pembina & Ketua Media.');
    }

    // ============================================================
    // WORKFLOW: 5. REQUEST REVISION
    // ============================================================

    public function requestRevision(Request $request, $id)
    {
        $project = Project::findOrFail($id);
        $user = Auth::user();

        if (!$user->isManager()) {
            return back()->with('error', 'Hanya Pembina/Ketua Media yang bisa minta revisi.');
        }

        if ($project->status !== Project::STATUS_REVIEW) {
            return back()->with('error', 'Project tidak dalam tahap Review.');
        }

        $request->validate([
            'note' => 'required|string|min:10',
        ], [
            'note.required' => 'Catatan revisi wajib diisi.',
            'note.min' => 'Catatan revisi minimal 10 karakter.',
        ]);

        ProjectRevision::create([
            'project_id' => $project->id,
            'user_id'    => $user->id,
            'type'       => 'revision',
            'note'       => $request->note,
        ]);

        $project->update([
            'status' => Project::STATUS_REVISION,
            'approved_by_pembina_at' => null,
            'approved_by_ketua_at' => null,
        ]);

        $memberIds = $project->users()->pluck('users.id')->toArray();

        $recipients = User::where(function ($q) use ($memberIds) {
                $q->where('division_role', 'kepala_divisi')
                  ->orWhereIn('id', $memberIds);
            })
            ->where('status', 'Aktif')
            ->get();

        $this->notifyUsers(
            $recipients,
            'revision_requested',
            null,
            fn($notifier, $u) => $notifier->revisionRequestedTemplate(
                $u->name,
                $project->name,
                $request->note,
                $project->id
            )
        );

        return redirect()->route('projects.show', $id)
            ->with('success', 'Catatan revisi tersimpan. Project masuk tahap Revision.');
    }

    // ============================================================
    // WORKFLOW: 6. APPROVE
    // ============================================================

    public function approve($id)
    {
        $project = Project::findOrFail($id);
        $user = Auth::user();

        if (!$user->isManager()) {
            return back()->with('error', 'Hanya Pembina/Ketua Media yang bisa menyetujui project.');
        }

        if ($project->status !== Project::STATUS_REVIEW) {
            return back()->with('error', 'Project tidak dalam tahap Review.');
        }

        $updateData = [];

        if ($user->isPembina() && !$project->approved_by_pembina_at) {
            $updateData['approved_by_pembina_at'] = now();
        }

        if ($user->isKetuaMedia() && !$project->approved_by_ketua_at) {
            $updateData['approved_by_ketua_at'] = now();
        }

        if (empty($updateData)) {
            return back()->with('error', 'Anda sudah menyetujui project ini sebelumnya.');
        }

        $project->update($updateData);
        $project->refresh();

        if ($project->approved_by_pembina_at && $project->approved_by_ketua_at) {

            $this->workflow->resolveOpenRevisions($project);

            $project->update(['status' => Project::STATUS_APPROVED]);
            $project->update(['status' => Project::STATUS_UPLOAD]);

            $recipients = User::where('division_role', 'kepala_divisi')
                ->where('status', 'Aktif')
                ->get();

            $this->notifyUsers(
                $recipients,
                'project_approved',
                $project->id,
                fn($notifier, $u) => $notifier->projectApprovedTemplate(
                    $u->name,
                    $project->name,
                    $project->id
                )
            );

            return redirect()->route('projects.show', $id)
                ->with('success', '🎉 Project disetujui kedua pihak! Berlanjut ke tahap Upload.');
        }

        return redirect()->route('projects.show', $id)
            ->with('success', 'Persetujuan Anda tersimpan. Menunggu persetujuan pihak lainnya.');
    }

    // ============================================================
    // WORKFLOW: 7. MARK COMPLETE
    // ⚡ FIX: Guard content + redirect dinamis
    // ============================================================

    public function markComplete($id)
    {
        $project = Project::findOrFail($id);
        $user = Auth::user();

        if (!$user->isManager() && !$user->isKepalaDivisi()) {
            return back()->with('error', 'Hanya Pembina/Ketua Media/Kepala Divisi yang bisa mark complete.');
        }

        if ($project->status !== Project::STATUS_UPLOAD) {
            return back()->with('error', 'Project tidak dalam tahap Upload.');
        }

        // ⚡ GUARD: minimal 1 konten (raw / stock / activity)
        $contentCount = Content::where('project_id', $project->id)->count();

        if ($contentCount === 0) {
            return redirect()
                ->route('contents.formActivity', ['project_id' => $project->id])
                ->with('error', 'Project belum bisa di-complete. Minimal upload 1 konten (raw/stock/activity) dulu. Kalau tidak ada bahan, upload Activity (mis: live kajian) dengan deskripsi sebagai dokumentasi.');
        }

        $project->update([
            'status' => Project::STATUS_COMPLETED,
            'completed_at' => now(),
            'is_overdue' => false,
        ]);

        $recipients = User::where(function ($q) {
                $q->where('division_role', 'kepala_divisi')
                  ->orWhere('division_role', 'pembina');
            })
            ->where('status', 'Aktif')
            ->get();

        $this->notifyUsers(
            $recipients,
            'project_completed',
            $project->id,
            fn($notifier, $u) => $notifier->projectCompletedTemplate(
                $u->name,
                $project->name,
                $project->id
            )
        );

        // ⚡ FIX: Redirect dinamis — Kepala Divisi ke halaman divisinya sendiri
        if ($user->isKepalaDivisi()) {
            $divisionMap = [
                'Lapangan'    => 'Tim Lapangan',
                'Editing'     => 'Tim Editing',
                'Broadcast'   => 'Tim Broadcast',
                'AI Research' => 'Tim AI Research',
            ];
            $divName = $user->division ?? '';
            $divisionRouteName = $divisionMap[$divName] ?? 'Tim Lapangan';

            return redirect()
                ->route('division.show', $divisionRouteName)
                ->with('success', '🎉 Project selesai!');
        }

        return redirect()->route('projects.index')
            ->with('success', '🎉 Project selesai!');
    }

    // ============================================================
    // LEGACY
    // ============================================================

    public function updateProjectStatus(Request $request, $id)
    {
        return back()->with('error', 'Gunakan tombol aksi workflow di halaman detail project.');
    }
}