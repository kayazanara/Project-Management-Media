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
        $tasksOverdueCount = ProjectTask::where('status', '!=', 'Completed')->whereHas('project', function($q) {
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
            $divActiveProjects = Project::whereHas('users', function($q) use ($memIds) {
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

        // 7. Peringkat Anggota (Leaderboard) Berdasarkan Task Selesai sebagai KONTRIBUTOR
        $topMembers = User::withCount(['contributedTasks' => function ($query) use ($start, $end) {
            $query->where('status', 'Completed')
                ->whereBetween('completed_at', [$start, $end]);
        }])
            ->orderBy('contributed_tasks_count', 'desc')
            ->orderBy('name', 'asc')
            ->get();

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
            'filter' => $filter,
            'startDate' => $startDate,
            'endDate' => $endDate,
        ];

        return view('dashboard.index', $data);
    }
}