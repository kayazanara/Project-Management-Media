<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Content;
use App\Models\ProjectTask;
use App\Models\Project;
use App\Models\User;
use App\Models\DailyReport;
use Carbon\Carbon;

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

        $startDate = $request->start_date;
        $endDate = $request->end_date;
        $pjName = $request->pj_name;
        $managerName = $request->manager_name;

        // Rentang pekan sebelumnya untuk perbandingan
        $prevStartDate = Carbon::parse($startDate)->subWeek()->format('Y-m-d');
        $prevEndDate = Carbon::parse($endDate)->subWeek()->format('Y-m-d');

        // 1. Total Anggota & Anggota Aktif
        $totalMembers = User::count();
        $activeMembersCount = ProjectTask::where('status', 'Completed')
            ->whereBetween('updated_at', [$startDate, $endDate])
            ->whereNotNull('user_id')
            ->distinct('user_id')
            ->count('user_id');

        // 2. Konten Terposting Pekan Ini (Diubah dari 'Ready' menjadi 'Uploaded')
        $postedContentsThisWeek = Content::where('status', 'Uploaded')
            ->whereBetween('updated_at', [$startDate, $endDate])
            ->count();
            
        $postedContentsPrevWeek = Content::where('status', 'Uploaded')
            ->whereBetween('updated_at', [$prevStartDate, $prevEndDate])
            ->count();

        // 3. 4 Divisi Utama Sesuai PRD Sistem
        $divisionList = ['Tim Lapangan', 'Tim Editing', 'Tim Broadcast', 'Tim AI Research'];
        $divisionPerformance = [];

        foreach ($divisionList as $divName) {
            $cleanDivName = str_replace('Tim ', '', $divName);
            $userIdsInDiv = User::where('divisi', 'LIKE', "%{$divName}%")
                ->orWhere('divisi', 'LIKE', "%{$cleanDivName}%")
                ->orWhere('division', 'LIKE', "%{$divName}%")
                ->orWhere('division', 'LIKE', "%{$cleanDivName}%")
                ->pluck('id');

            $projectIds = \DB::table('project_user')->whereIn('user_id', $userIdsInDiv)->pluck('project_id');

            // Project yang dirilis / dibuat di pekan itu (murni data riil)
            $assignedThisWeek = Project::whereIn('id', $projectIds)
                ->whereBetween('created_at', [$startDate, $endDate])
                ->count();

            // Project selesai (Completed) pekan ini
            $completedThisWeek = Project::whereIn('id', $projectIds)
                ->where('status', 'Completed')
                ->whereBetween('updated_at', [$startDate, $endDate])
                ->count();

            // Project selesai pekan lalu
            $completedPrevWeek = Project::whereIn('id', $projectIds)
                ->where('status', 'Completed')
                ->whereBetween('updated_at', [$prevStartDate, $prevEndDate])
                ->count();

            // Overdue spesifik
            $overdue = Project::whereIn('id', $projectIds)
                ->where(function($q) {
                    $q->where('status', 'Overdue')
                      ->orWhere(function($sub) {
                          $sub->where('status', '!=', 'Completed')
                              ->where('deadline', '<', Carbon::now());
                      });
                })->count();

            // Ambil data kendala riil dari tabel daily_reports pada pekan ini
            $latestReport = DailyReport::whereBetween('report_date', [$startDate, $endDate])
                ->where(function($q) use ($divName, $cleanDivName) {
                    $q->where('division', 'LIKE', "%{$divName}%")
                      ->orWhere('division', 'LIKE', "%{$cleanDivName}%");
                })
                ->orderBy('report_date', 'desc')
                ->first();

            $issues = ($latestReport && !empty(trim($latestReport->problems))) 
                ? $latestReport->problems 
                : "Belum ada laporan";

            // Ambil kendala pekan sebelumnya dari tabel daily_reports
            $prevReport = DailyReport::whereBetween('report_date', [$prevStartDate, $prevEndDate])
                ->where(function($q) use ($divName, $cleanDivName) {
                    $q->where('division', 'LIKE', "%{$divName}%")
                      ->orWhere('division', 'LIKE', "%{$cleanDivName}%");
                })
                ->orderBy('report_date', 'desc')
                ->first();

            $prevIssues = ($prevReport && !empty(trim($prevReport->problems))) 
                ? $prevReport->problems 
                : "Belum ada laporan";

            $divisionPerformance[] = [
                'name' => $divName,
                'assigned' => $assignedThisWeek,
                'completed' => $completedThisWeek,
                'prev_completed' => $completedPrevWeek,
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