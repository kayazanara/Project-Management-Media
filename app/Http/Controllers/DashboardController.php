<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Project;
use App\Models\ProjectTask;
use App\Models\User;
use App\Models\Content;
use Carbon\Carbon;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        // 1. Ambil Parameter Filter dari Request (Default: 'week')
        $filter = $request->input('filter', 'week');
        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');

        // Tentukan rentang tanggal berdasarkan filter
        $now = Carbon::now();
        if ($startDate && $endDate) {
            $start = Carbon::parse($startDate)->startOfDay();
            $end = Carbon::parse($endDate)->endOfDay();
        } else {
            switch ($filter) {
                case 'month':
                    $start = $now->copy()->startOfMonth();
                    $end = $now->copy()->endOfMonth();
                    break;
                case 'year':
                    $start = $now->copy()->startOfYear();
                    $end = $now->copy()->endOfYear();
                    break;
                case 'week':
                default:
                    $start = $now->copy()->startOfWeek();
                    $end = $now->copy()->endOfWeek();
                    break;
            }
        }

        // 2. KPI / Statistik Utama
        $totalProjects = Project::count();
        $activeProjectsCount = Project::where('status', '!=', 'Completed')->count();
        $today = Carbon::today()->toDateString();

        $tasksTodayCount = ProjectTask::whereDate('task_date', $today)->count();
        $tasksCompletedTodayCount = ProjectTask::where('status', 'Completed')->whereDate('completed_at', $today)->count();
        $tasksOverdueCount = ProjectTask::where('status', '!=', 'Completed')->whereHas('project', function ($q) {
            $q->where('deadline', '<', now()->toDateString());
        })->count();

        $totalMembers = User::count();
        $readyStockCount = Content::where('status', 'Ready')->count();

        // 3. Project Berjalan (Aktif)
        $activeProjects = Project::where('status', '!=', 'Completed')->with('users')->get();

        // 4. Task Hari Ini
        $todayTasks = ProjectTask::whereDate('task_date', $today)->with(['project', 'assignedUser'])->get();

        // 5. Aktivitas Divisi
        $divisions = ['Tim Lapangan', 'Tim Editing', 'Tim Broadcast', 'Tim AI Research'];
        $divisionActivities = [];

        foreach ($divisions as $divName) {
            $keyword = trim(str_replace('Tim', '', $divName));
            $memIds = User::where('division', 'like', '%' . $keyword . '%')->pluck('id');

            $divMembersCount = $memIds->count();
            $divActiveProjects = Project::whereHas('users', function ($q) use ($memIds) {
                $q->whereIn('users.id', $memIds);
            })->where('status', '!=', 'Completed')->count();

            $divTasksToday = ProjectTask::whereIn('assigned_to', $memIds)->whereDate('task_date', $today)->count();

            $divisionActivities[] = [
                'name' => $divName,
                'members_count' => $divMembersCount,
                'active_projects' => $divActiveProjects,
                'tasks_today' => $divTasksToday,
            ];
        }

        // 6. Data untuk Chart Task Berdasarkan Rentang Waktu yang Dipilih
        $chartLabels = [];
        $chartData = [];

        $period = Carbon::parse($start)->daysUntil($end);
        foreach ($period as $date) {
            $chartLabels[] = $date->format('d M');
            $chartData[] = ProjectTask::where('status', 'Completed')
                ->whereDate('completed_at', $date->toDateString())
                ->count();
        }

        // ==========================================
        // 7. ⭐ PERINGKAT ANGGOTA — SISTEM POIN
        // ==========================================
        // ⚡ FIX (B1): poin dihitung dari tanggal SELESAI (completed_at) pada periode terpilih,
        // sama seperti halaman Monitoring. Sebelumnya pakai tanggal RENCANA (task_date) sehingga
        // poin bisa berbeda antar halaman dan tugas bertanggal ke depan ikut terbuang.
        $tasksInPeriod = ProjectTask::where('status', 'Completed')
            ->whereBetween('completed_at', [$start, $end])
            ->with(['contributors:id', 'assignedUser:id'])
            ->get();

        // Hitung poin per user
        $userPoints = [];
        $userTaskCounts = [];

        foreach ($tasksInPeriod as $task) {
            $weight = (float) $task->weight;
            if ($weight <= 0) {
                continue;
            }

            // Tentukan daftar contributor
            $contributorIds = $task->contributors->pluck('id')->toArray();

            // Fallback ke PIC kalau tidak ada contributor
            if (empty($contributorIds) && $task->assigned_to) {
                $contributorIds = [(int) $task->assigned_to];
            }

            if (empty($contributorIds)) {
                continue;
            }

            // Bagi poin rata
            $pointPerPerson = $weight / count($contributorIds);

            foreach ($contributorIds as $uid) {
                $userPoints[$uid] = ($userPoints[$uid] ?? 0) + $pointPerPerson;
                $userTaskCounts[$uid] = ($userTaskCounts[$uid] ?? 0) + 1;
            }
        }

        // Ambil user yang punya poin, map, dan sort
        // FITUR BARU: gabungkan poin bonus login harian (sumber tunggal: DailyLoginReward)
        $poinLogin = app(\App\Services\DailyLoginReward::class)->pointsPerUser($start, $end);

        $topMembers = User::whereIn('id', array_keys($userPoints))
            ->get()
            ->map(function ($u) use ($userPoints, $userTaskCounts, $poinLogin) {
                $u->points = round($userPoints[$u->id] ?? 0) + ($poinLogin[$u->id] ?? 0);
                $u->task_points_count = $userTaskCounts[$u->id] ?? 0;
                return $u;
            })
            ->sortByDesc('points')
            ->values();

        // ==========================================
        // 8. Task Selesai (Grouped by Day)
        // ==========================================
        $completedTasksRaw = ProjectTask::where('status', 'Completed')
            ->whereBetween('completed_at', [$start, $end])
            ->with(['project', 'assignedUser', 'contributors'])
            ->orderBy('completed_at', 'desc')
            ->get();

        $completedTasksByDay = $completedTasksRaw
            ->groupBy(fn($task) => Carbon::parse($task->completed_at)->format('Y-m-d'))
            ->map(function ($tasks, $date) {
                return [
                    'date' => $date,
                    'date_formatted' => Carbon::parse($date)->translatedFormat('l, d F Y'),
                    'day_name' => Carbon::parse($date)->translatedFormat('l'),
                    'tasks' => $tasks,
                    'count' => $tasks->count(),
                ];
            })
            ->values();

        $totalCompletedTasks = $completedTasksRaw->count();

        $data = [
            'title' => 'Dashboard Utama',
            'totalProjects' => $totalProjects,
            'activeProjectsCount' => $activeProjectsCount,
            'tasksTodayCount' => $tasksTodayCount,
            'tasksCompletedTodayCount' => $tasksCompletedTodayCount,
            'tasksOverdueCount' => $tasksOverdueCount,
            'totalMembers' => $totalMembers,
            'readyStockCount' => $readyStockCount,
            'activeProjects' => $activeProjects,
            'todayTasks' => $todayTasks,
            'divisionActivities' => $divisionActivities,
            'chartLabels' => $chartLabels,
            'chartData' => $chartData,
            'topMembers' => $topMembers,
            'completedTasksByDay' => $completedTasksByDay,
            'totalCompletedTasks' => $totalCompletedTasks,
            'filter' => $filter,
            'startDate' => $startDate,
            'endDate' => $endDate,
        ];

        return view('dashboard.index', $data);
    }
}