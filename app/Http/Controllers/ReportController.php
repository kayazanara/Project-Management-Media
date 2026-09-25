<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Content;
use App\Models\ProjectTask;
use App\Models\Project;
use App\Models\User;
use App\Models\DailyReport;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class ReportController extends Controller
{
    public function index()
    {
        $data = [
            'title' => 'Cetak Laporan & Monitoring',
            'start_date' => Carbon::now()->startOfWeek()->format('Y-m-d'),
            'end_date' => Carbon::now()->endOfWeek()->format('Y-m-d'),
            'users' => User::all(),
        ];
        return view('reports.index', $data);
    }

    public function generate(Request $request)
    {
        $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'pj_name' => 'required|string',
            'manager_name' => 'required|string',
        ]);

        // =============================================
        // ⭐ FIX #3: Pakai startOfDay & endOfDay
        // =============================================
        $start = Carbon::parse($request->start_date)->startOfDay();
        $end = Carbon::parse($request->end_date)->endOfDay();

        $prevStart = Carbon::parse($request->start_date)->subWeek()->startOfDay();
        $prevEnd = Carbon::parse($request->end_date)->subWeek()->endOfDay();

        $startDate = $start->format('Y-m-d');
        $endDate = $end->format('Y-m-d');
        $pjName = $request->pj_name;
        $managerName = $request->manager_name;

        // =============================================
        // ⭐ FIX #1: Anggota Aktif pakai KONTRIBUTOR (pivot)
        // =============================================
        $totalMembers = User::count();

        $activeMemberIds = DB::table('project_task_contributors')
            ->join('project_tasks', 'project_task_contributors.project_task_id', '=', 'project_tasks.id')
            ->where('project_tasks.status', 'Completed')
            ->whereBetween('project_tasks.completed_at', [$start, $end])
            ->distinct()
            ->pluck('project_task_contributors.user_id')
            ->unique();

        $activeMembersCount = $activeMemberIds->count();

        // =============================================
        // ⭐ FIX: Content pakai `uploaded_at` (bukan updated_at)
        // =============================================
        $postedContentsThisWeek = Content::where('status', 'Uploaded')
            ->whereBetween('uploaded_at', [$start, $end])
            ->count();

        $postedContentsPrevWeek = Content::where('status', 'Uploaded')
            ->whereBetween('uploaded_at', [$prevStart, $prevEnd])
            ->count();

        // =============================================
        // Loop per divisi
        // =============================================
        $divisionList = ['Tim Lapangan', 'Tim Editing', 'Tim Broadcast', 'Tim AI Research'];
        $divisionPerformance = [];

        foreach ($divisionList as $divName) {
            $cleanDivName = str_replace('Tim ', '', $divName);

            // ⭐ FIX #2: Pakai kolom `division` (bukan `divisi`)
            $memberIds = User::where(function ($q) use ($divName, $cleanDivName) {
                    $q->where('division', 'like', "%{$cleanDivName}%")
                      ->orWhere('division', 'like', "%{$divName}%");
                })
                ->pluck('id');

            // Project yang melibatkan anggota divisi ini
            $projectIds = DB::table('project_user')
                ->whereIn('user_id', $memberIds)
                ->pluck('project_id')
                ->unique();

            // ==========================================
            // Project Baru = project yang dibuat di periode
            // ==========================================
            $projectsCreatedThisWeek = Project::whereIn('id', $projectIds)
                ->whereBetween('created_at', [$start, $end])
                ->count();

            // ==========================================
            // ⭐ FIX #4: Task Selesai pakai `completed_at` + KONTRIBUTOR
            // ==========================================
            $completedTasksThisWeek = ProjectTask::where('status', 'Completed')
                ->whereBetween('completed_at', [$start, $end])
                ->whereHas('contributors', function ($q) use ($memberIds) {
                    $q->whereIn('users.id', $memberIds);
                })
                ->distinct('project_tasks.id')
                ->count('project_tasks.id');

            $completedTasksPrevWeek = ProjectTask::where('status', 'Completed')
                ->whereBetween('completed_at', [$prevStart, $prevEnd])
                ->whereHas('contributors', function ($q) use ($memberIds) {
                    $q->whereIn('users.id', $memberIds);
                })
                ->distinct('project_tasks.id')
                ->count('project_tasks.id');

            // ==========================================
            // Project Completed pakai `completed_at`
            // ==========================================
            $completedProjectsThisWeek = Project::whereIn('id', $projectIds)
                ->where('status', 'Completed')
                ->whereBetween('completed_at', [$start, $end])
                ->count();

            $completedProjectsPrevWeek = Project::whereIn('id', $projectIds)
                ->where('status', 'Completed')
                ->whereBetween('completed_at', [$prevStart, $prevEnd])
                ->count();

            // ==========================================
            // ⭐ FIX #6: Overdue — project dengan status Overdue / deadline lewat
            //           (semua yang belum Completed & deadline < end periode)
            // ==========================================
            $overdue = Project::whereIn('id', $projectIds)
                ->where('status', '!=', 'Completed')
                ->where('deadline', '<', $end->toDateString())
                ->count();

            // ==========================================
            // ⭐ FIX BUG BARU: Ambil SEMUA kendala dalam periode
            //                Gabung dengan tanggal: [dd/mm] masalah
            // ==========================================
            $reports = DailyReport::whereBetween('report_date', [$startDate, $endDate])
                ->where(function ($q) use ($divName, $cleanDivName) {
                    $q->where('division', 'like', "%{$divName}%")
                      ->orWhere('division', 'like', "%{$cleanDivName}%");
                })
                ->orderBy('report_date', 'asc')
                ->get();

            $problemList = [];
            foreach ($reports as $r) {
                if (!empty(trim($r->problems))) {
                    $dateShort = Carbon::parse($r->report_date)->format('d/m');
                    $problemList[] = "[{$dateShort}] " . trim($r->problems);
                }
            }

            $issues = count($problemList) > 0
                ? implode("\n", $problemList)
                : "Belum ada laporan";

            // ==========================================
            // ⭐ FIX BUG BARU: Kendala pekan sebelumnya — gabungkan semua
            // ==========================================
            $prevReports = DailyReport::whereBetween('report_date', [$prevStartDate, $prevEndDate])
                ->where(function ($q) use ($divName, $cleanDivName) {
                    $q->where('division', 'like', "%{$divName}%")
                      ->orWhere('division', 'like', "%{$cleanDivName}%");
                })
                ->orderBy('report_date', 'asc')
                ->get();

            $prevProblemList = [];
            foreach ($prevReports as $r) {
                if (!empty(trim($r->problems))) {
                    $dateShort = Carbon::parse($r->report_date)->format('d/m');
                    $prevProblemList[] = "[{$dateShort}] " . trim($r->problems);
                }
            }

            $prevIssues = count($prevProblemList) > 0
                ? implode("\n", $prevProblemList)
                : "Belum ada laporan";

            $divisionPerformance[] = [
                'name' => $divName,
                'assigned' => $projectsCreatedThisWeek,
                'completed' => $completedTasksThisWeek,
                'prev_completed' => $completedTasksPrevWeek,
                'completed_projects' => $completedProjectsThisWeek,
                'prev_completed_projects' => $completedProjectsPrevWeek,
                'overdue' => $overdue,
                'issues' => $issues,
                'prev_issues' => $prevIssues,
            ];
        }

        $data = [
            'title' => 'Laporan Kinerja & Evaluasi Divisi Media',
            'startDate' => $startDate,
            'endDate' => $endDate,
            'pjName' => $pjName,
            'managerName' => $managerName,
            'metrics' => [
                'total_members' => $totalMembers,
                'active_members' => $activeMembersCount,
                'posted_contents' => $postedContentsThisWeek,
                'prev_posted_contents' => $postedContentsPrevWeek,
            ],
            'divisions' => $divisionPerformance,
        ];

        return view('reports.print', $data);
    }
}