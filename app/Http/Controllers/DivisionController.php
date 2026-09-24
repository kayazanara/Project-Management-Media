<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Project;
use App\Models\ProjectTask;
use App\Models\DailyReport;
use Illuminate\Support\Facades\Auth;

class DivisionController extends Controller
{
    public function show(Request $request, $divisionName)
    {
        $decodedDivision = urldecode($divisionName);
        $keyword = trim(str_replace('Tim', '', $decodedDivision));

        // Anggota divisi
        $members = User::where('division', 'like', '%' . $keyword . '%')->get();
        $memberIds = $members->pluck('id');

        // Project aktif + tasks di-load, urut Pending dulu baru Completed
        $projects = Project::whereHas('users', function ($q) use ($memberIds) {
            $q->whereIn('users.id', $memberIds);
        })
            ->where('status', '!=', 'Completed')
            ->with([
                'users',
                'tasks' => function ($q) {
                    $q->with(['assignedUser', 'contributors'])
                      ->orderByRaw("CASE WHEN status = 'Completed' THEN 1 ELSE 0 END")
                      ->orderBy('task_date', 'asc');
                }
            ])
            ->get();

        // Project selesai
        $completedProjects = Project::whereHas('users', function ($q) use ($memberIds) {
            $q->whereIn('users.id', $memberIds);
        })
            ->where('status', 'Completed')
            ->with(['users', 'tasks.assignedUser'])
            ->get();

        // Semua task dari project aktif — untuk modal pilih kontributor
        $activeProjectIds = $projects->pluck('id');
        $tasks = ProjectTask::whereIn('project_id', $activeProjectIds)
            ->with(['project.users', 'assignedUser', 'contributors'])
            ->orderByRaw("CASE WHEN status = 'Completed' THEN 1 ELSE 0 END")
            ->orderBy('task_date', 'asc')
            ->get();

        // Riwayat Laporan dengan Filter
        $query = DailyReport::where('division', $decodedDivision)->with(['user', 'project']);

        if ($request->has('filter') && $request->filter != '') {
            $filter = $request->filter;
            if ($filter == 'week') {
                $query->whereBetween('report_date', [now()->startOfWeek(), now()->endOfWeek()]);
            } elseif ($filter == 'month') {
                $query->whereMonth('report_date', now()->month)
                      ->whereYear('report_date', now()->year);
            } elseif ($filter == 'year') {
                $query->whereYear('report_date', now()->year);
            }
        }

        $dailyReports = $query->orderBy('report_date', 'desc')->get();

        $data = [
            'title' => 'Monitoring Divisi: ' . $decodedDivision,
            'divisionName' => $decodedDivision,
            'members' => $members,
            'projects' => $projects,
            'completedProjects' => $completedProjects,
            'tasks' => $tasks,
            'dailyReports' => $dailyReports,
            'currentFilter' => $request->filter ?? ''
        ];

        return view('divisions.show', $data);
    }

    public function destroyReport($id)
    {
        $report = DailyReport::findOrFail($id);
        $division = $report->division;
        $report->delete();

        return redirect()->route('division.show', $division)->with('success', 'Laporan harian berhasil dihapus!');
    }

    public function storeReport(Request $request, $divisionName)
    {
        $decodedDivision = urldecode($divisionName);

        $request->validate([
            'report_date' => 'required|date',
            'project_id' => 'nullable|exists:projects,id',
            'notes' => 'nullable|string',
            'problems' => 'nullable|string',
            'next_plan' => 'nullable|string',
        ]);

        $keyword = trim(str_replace('Tim', '', $decodedDivision));
        $memberIds = User::where('division', 'like', '%' . $keyword . '%')->pluck('id');

        $reportDate = $request->report_date;

        $completedTasks = ProjectTask::whereIn('assigned_to', $memberIds)
            ->where('status', 'Completed')
            ->whereDate('completed_at', $reportDate)
            ->pluck('title')
            ->toArray();

        $completedWorkText = count($completedTasks) > 0
            ? implode(', ', $completedTasks)
            : 'Tidak ada task baru yang diselesaikan pada tanggal ' . $reportDate;

        $unfinishedTasks = ProjectTask::whereIn('assigned_to', $memberIds)
            ->where('status', '!=', 'Completed')
            ->pluck('title')
            ->toArray();

        DailyReport::create([
            'user_id' => Auth::id() ?? 1,
            'division' => $decodedDivision,
            'project_id' => $request->project_id,
            'report_date' => $reportDate,
            'completed_work' => $completedWorkText,
            'unfinished_work' => implode(', ', $unfinishedTasks) ?: 'Semua task selesai.',
            'problems' => $request->problems,
            'next_plan' => $request->next_plan,
            'notes' => $request->notes,
        ]);

        return redirect()->route('division.show', $decodedDivision)->with('success', 'Laporan harian berhasil dibuat tanpa duplikasi task!');
    }
}