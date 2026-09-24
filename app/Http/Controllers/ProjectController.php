<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Project;
use App\Models\User;
use App\Models\ProjectTask;
use App\Events\ProjectCreated;

class ProjectController extends Controller
{
    // Menampilkan daftar project, memisahkan aktif dan selesai, serta menghitung statistik status
        public function index()
    {
        // Otomatis update status menjadi Overdue jika melewati deadline dan belum Completed
        Project::where('status', '!=', 'Completed')
            ->where('status', '!=', 'Overdue')
            ->where('deadline', '<', now()->toDateString())
            ->update(['status' => 'Overdue']);

        // Proyek Aktif (Selain Completed) + hitung progress via withCount
        $projects = Project::where('status', '!=', 'Completed')
            ->with('users')
            ->withCount([
                'tasks as total_tasks_count',
                'tasks as completed_tasks_count' => function ($q) {
                    $q->where('status', 'Completed');
                },
            ])
            ->get();

        // Riwayat Proyek Selesai (Khusus Completed)
        $completedProjects = Project::where('status', 'Completed')
            ->with('users')
            ->withCount([
                'tasks as total_tasks_count',
                'tasks as completed_tasks_count' => function ($q) {
                    $q->where('status', 'Completed');
                },
            ])
            ->get();

        // Hitung statistik jumlah berdasarkan status project
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

    // Menampilkan form tambah project
    public function create()
    {
        $users = User::all();
        $data = [
            'title' => 'Tambah Project Baru',
            'users' => $users
        ];

        return view('projects.create', $data);
    }

    // Menyimpan data project baru
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

        // 🔔 Kirim notifikasi WA ke semua anggota project (via event → listener → job)
        ProjectCreated::dispatch($project);

        return redirect()->route('projects.index')->with('success', 'Project baru berhasil ditambahkan!');
    }

    // Menampilkan form edit project
    public function edit($id)
    {
        $project = Project::with('users')->findOrFail($id);
        $users = User::all();
        
        $data = [
            'title' => 'Edit Project',
            'project' => $project,
            'users' => $users
        ];

        return view('projects.edit', $data);
    }

    // Mengupdate data project
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
        ], [
            'name.required' => 'Nama project wajib diisi.',
            'user_ids.required' => 'Minimal harus memilih satu orang yang terlibat dalam project.',
            'user_ids.min' => 'Minimal harus memilih satu orang yang terlibat dalam project.',
            'start_date.required' => 'Tanggal masuk project wajib diisi.',
            'deadline.required' => 'Tanggal deadline wajib diisi.',
            'deadline.after_or_equal' => 'Deadline tidak boleh lebih awal dari tanggal mulai project.',
        ]);

        $project = Project::findOrFail($id);
        
        $project->update([
            'name' => $request->name,
            'start_date' => $request->start_date,
            'deadline' => $request->deadline,
            'reference_link' => $request->reference_link,
            'brief' => $request->brief,
        ]);

        // Sinkronisasi ulang relasi user yang terlibat
        $project->users()->sync($request->user_ids);

        return redirect()->route('projects.index')->with('success', 'Project berhasil diupdate!');
    }

    // Menghapus project
    public function destroy($id)
    {
        $project = Project::findOrFail($id);
        $project->users()->detach(); // Menghapus relasi dari pivot table
        $project->delete();

        return redirect()->route('projects.index')->with('success', 'Project berhasil dihapus!');
    }

    // Menampilkan detail project, task, progress otomatis, dan pengecekan otomatis Overdue
    public function show($id)
    {
        $project = Project::with(['users', 'tasks.assignedUser'])->findOrFail($id);
        
        // Cek otomatis Overdue: jika deadline sudah lewat dari hari ini dan status belum Completed
        if ($project->status != 'Completed' && $project->deadline < now()->toDateString()) {
            if ($project->status != 'Overdue') {
                $project->update(['status' => 'Overdue']);
                $project->refresh();
            }
        }
        
        // Hitung Progress Otomatis (Completed Task / Total Task * 100)
        $totalTasks = $project->tasks->count();
        $completedTasks = $project->tasks->where('status', 'Completed')->count();
        $progress = $totalTasks > 0 ? round(($completedTasks / $totalTasks) * 100) : 0;

        $users = User::all();

        $data = [
            'title' => 'Detail Project: ' . $project->name,
            'project' => $project,
            'progress' => $progress,
            'users' => $users
        ];

        return view('projects.show', $data);
    }

    // Menyimpan task baru ke dalam project
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

    // Mengubah status task (Pending <-> Completed) sekaligus memperbarui progress otomatis
        /**
     * Mengubah status task (Pending <-> Completed)
     * - Saat Completed: wajib pilih kontributor (multi-select)
     * - Saat Reopen (Completed → Pending): kontributor tetap tersimpan
     */
    public function toggleTask(Request $request, $id)
    {
        $task = ProjectTask::findOrFail($id);

        // ============================================
        // KASUS 1: REOPEN (Completed → Pending)
        // Kontributor TIDAK dihapus
        // ============================================
        if ($task->status == 'Completed') {
            $task->status = 'Pending';
            $task->completed_at = null;
            $task->save();

            return redirect()->back()->with('success', 'Task dibuka kembali. Kontributor tetap tersimpan.');
        }

        // ============================================
        // KASUS 2: COMPLETE (Pending → Completed)
        // Wajib pilih kontributor
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

        // Sync kontributor (yang tidak dicentang akan dihapus, yang dicentang disimpan)
        $task->contributors()->sync($request->contributors);

        $names = User::whereIn('id', $request->contributors)->pluck('name')->implode(', ');

        return redirect()->back()->with('success', "Task selesai! Kontributor: {$names}");
    }

    // Menghapus task
    public function destroyTask($id)
    {
        $task = ProjectTask::findOrFail($id);
        $projectId = $task->project_id;
        $task->delete();

        return redirect()->route('projects.show', $projectId)->with('success', 'Task berhasil dihapus!');
    }

    // Menampilkan form edit task
    public function editTask($id)
    {
        $task = ProjectTask::with('project.users')->findOrFail($id);
        
        $data = [
            'title' => 'Edit Task: ' . $task->title,
            'task' => $task,
            'project' => $task->project
        ];

        return view('projects.edit-task', $data);
    }

    // Memperbarui data task
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

    // Memperbarui status workflow project
    public function updateProjectStatus(Request $request, $id)
    {
        $request->validate([
            'status' => 'required|string|in:Planning,On Progress,Review,Revision,Completed,Overdue',
        ]);

        $project = Project::findOrFail($id);
        $project->update([
            'status' => $request->status,
        ]);

        return redirect()->route('projects.show', $id)->with('success', 'Status workflow project berhasil diperbarui menjadi ' . $request->status . '!');
    }
}