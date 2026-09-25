<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Project;
use App\Models\User;
use App\Models\ProjectTask;
use App\Events\ProjectCreated;

class ProjectController extends Controller
{
    /**
     * Cek apakah user adalah Manager (Pembina/Ketua/Admin/Ustadz)
     */
    private function isManager($user): bool
    {
        if (!$user) return false;
        $userDiv = strtolower(trim($user->division ?? ''));
        $userRole = strtolower(trim($user->role ?? ''));
        return str_contains($userDiv, 'pembina')
            || str_contains($userDiv, 'ketua')
            || $userRole === 'administrator'
            || str_contains($userRole, 'ustadz');
    }

    public function index()
    {
        Project::where('status', '!=', 'Completed')
            ->where('status', '!=', 'Overdue')
            ->where('deadline', '<', now()->toDateString())
            ->update(['status' => 'Overdue']);

        $projects = Project::where('status', '!=', 'Completed')
            ->with('users')
            ->withCount([
                'tasks as total_tasks_count',
                'tasks as completed_tasks_count' => function ($q) {
                    $q->where('status', 'Completed');
                },
            ])
            ->get();

        $completedProjects = Project::where('status', 'Completed')
            ->with('users')
            ->withCount([
                'tasks as total_tasks_count',
                'tasks as completed_tasks_count' => function ($q) {
                    $q->where('status', 'Completed');
                },
            ])
            ->orderBy('completed_at', 'desc')
            ->get();

        $countPlanning = Project::where('status', 'Planning')->count();
        $countOnProgress = Project::where('status', 'On Progress')->count();
        $countReview = Project::where('status', 'Review')->count();
        $countRevision = Project::where('status', 'Revision')->count();
        $countOverdue = Project::where('status', 'Overdue')->count();

        $data = [
            'title' => 'Project Management',
            'projects' => $projects,
            'completedProjects' => $completedProjects,
            'countPlanning' => $countPlanning,
            'countOnProgress' => $countOnProgress,
            'countReview' => $countReview,
            'countRevision' => $countRevision,
            'countOverdue' => $countOverdue,
        ];

        return view('projects.index', $data);
    }

    public function create()
    {
        $users = User::all();
        return view('projects.create', [
            'title' => 'Tambah Project Baru',
            'users' => $users
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
            'status' => 'Planning',
            'reference_link' => $request->reference_link,
            'brief' => $request->brief,
        ]);

        $project->users()->attach($request->user_ids);

        ProjectCreated::dispatch($project);

        return redirect()->route('projects.index')->with('success', 'Project baru berhasil ditambahkan!');
    }

    public function edit($id)
    {
        $project = Project::with('users')->findOrFail($id);
        $users = User::all();

        return view('projects.edit', [
            'title' => 'Edit Project',
            'project' => $project,
            'users' => $users
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

        return redirect()->route('projects.index')->with('success', 'Project berhasil diupdate!');
    }

    public function destroy($id)
    {
        $project = Project::findOrFail($id);
        $project->users()->detach();
        $project->delete();

        return redirect()->route('projects.index')->with('success', 'Project berhasil dihapus!');
    }

    public function show($id)
    {
        $project = Project::with(['users', 'tasks.assignedUser', 'tasks.contributors'])->findOrFail($id);

        if ($project->status != 'Completed' && $project->deadline < now()->toDateString()) {
            if ($project->status != 'Overdue') {
                $project->update(['status' => 'Overdue']);
                $project->refresh();
            }
        }

        $totalTasks = $project->tasks->count();
        $completedTasks = $project->tasks->where('status', 'Completed')->count();
        $progress = $totalTasks > 0 ? round(($completedTasks / $totalTasks) * 100) : 0;

        $users = User::all();

        return view('projects.show', [
            'title' => 'Detail Project: ' . $project->name,
            'project' => $project,
            'progress' => $progress,
            'users' => $users
        ]);
    }

    public function storeTask(Request $request, $id)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'assigned_to' => 'nullable|exists:users,id',
            'task_date' => 'nullable|date',
            'description' => 'nullable|string',
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
        $project = $task->project;

        // ============================================
        // KASUS 1: REOPEN (Completed → Pending)
        // ============================================
        if ($task->status == 'Completed') {
            $task->status = 'Pending';
            $task->completed_at = null;
            $task->save();

            return redirect()->back()->with('success', 'Task dibuka kembali. Kontributor tetap tersimpan.');
        }

        // ============================================
        // KASUS 2: COMPLETE (Pending → Completed)
        // ============================================
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

        // ============================================
        // CEK: Apakah ini task pertama yang selesai
        // & status project masih Planning?
        // → Tawarkan ubah ke On Progress
        // ============================================
        $completedCount = $project->tasks()->where('status', 'Completed')->count();

        if ($completedCount === 1 && $project->status === 'Planning') {
            return redirect()->route('projects.show', $project->id)
                ->with('success', "Task selesai! Kontributor: {$names}")
                ->with('suggest_on_progress', true);
        }

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
            'project' => $task->project
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

    /**
     * Update status workflow project — dengan validasi role & transisi.
     *
     * Aturan:
     * - Completed: tidak bisa diubah lagi
     * - Overdue: hanya bisa → Completed, hanya oleh Manager
     * - Santri: Planning → On Progress → Review (hanya ini)
     * - Manager: bebas semua transisi (kecuali dari Completed)
     *
     * ⭐ Update: auto-set `completed_at` saat status = Completed
     */
    public function updateProjectStatus(Request $request, $id)
    {
        $request->validate([
            'status' => 'required|string|in:Planning,On Progress,Review,Revision,Completed,Overdue',
        ]);

        $project = Project::findOrFail($id);
        $user = auth()->user();
        $isManager = $this->isManager($user);
        $newStatus = $request->status;
        $oldStatus = $project->status;

        // === Rule 1: Project Completed → tidak bisa diubah ===
        if ($oldStatus === 'Completed') {
            return redirect()->back()->with('error', 'Project sudah selesai dan tidak bisa diubah lagi.');
        }

        // === Rule 2: Project Overdue → hanya Completed (oleh Manager) ===
        if ($oldStatus === 'Overdue') {
            if (!$isManager) {
                return redirect()->back()->with('error', 'Project Overdue hanya bisa diubah oleh Pembina/Ketua Media.');
            }
            if ($newStatus !== 'Completed') {
                return redirect()->back()->with('error', 'Project Overdue hanya bisa diubah ke Completed.');
            }
        }

        // === Rule 3: Santri → hanya transisi terbatas ===
        if (!$isManager) {
            $allowedTransitions = [
                'Planning'    => ['On Progress'],
                'On Progress' => ['Review'],
                'Review'      => ['On Progress'],
                'Revision'    => ['Review'],
            ];

            $isAllowed = isset($allowedTransitions[$oldStatus])
                && in_array($newStatus, $allowedTransitions[$oldStatus]);

            if (!$isAllowed) {
                return redirect()->back()->with('error', "Anda tidak punya akses untuk mengubah status dari {$oldStatus} ke {$newStatus}.");
            }
        }

        // === ⭐ Auto-set completed_at kalau berubah ke Completed ===
        $updateData = ['status' => $newStatus];

        if ($newStatus === 'Completed') {
            $updateData['completed_at'] = now();
        } else {
            // Kalau diubah dari Completed ke status lain (tidak mungkin karena Rule 1)
            // atau diubah dari status lain, reset completed_at
            $updateData['completed_at'] = null;
        }

        $project->update($updateData);

        return redirect()->route('projects.show', $id)
            ->with('success', 'Status workflow project berhasil diperbarui menjadi ' . $newStatus . '!');
    }
}