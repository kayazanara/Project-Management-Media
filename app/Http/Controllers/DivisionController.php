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
    // Menampilkan halaman monitoring & laporan harian dengan opsi filter
    public function show(Request $request, $divisionName)
    {
        $decodedDivision = urldecode($divisionName);
        $keyword = trim(str_replace('Tim', '', $decodedDivision));

        // Ambil anggota divisi
        $members = User::where('division', 'like', '%' . $keyword . '%')->get();
        $memberIds = $members->pluck('id');

        // Ambil project aktif (selain Completed) untuk statistik dan tabel project aktif
        $projects = Project::whereHas('users', function($q) use ($memberIds) {
            $q->whereIn('users.id', $memberIds);
        })->where('status', '!=', 'Completed')
          ->with(['users', 'tasks.assignedUser'])
          ->get();

        // Ambil project selesai (khusus status Completed) untuk Riwayat Project Selesai
        $completedProjects = Project::whereHas('users', function($q) use ($memberIds) {
            $q->whereIn('users.id', $memberIds);
        })->where('status', 'Completed')
          ->with(['users', 'tasks.assignedUser'])
          ->get();

        // Ambil task aktif (hanya dari project aktif yang belum Completed)
        $activeProjectIds = $projects->pluck('id');
        $tasks = ProjectTask::whereIn('project_id', $activeProjectIds)
            ->whereIn('assigned_to', $memberIds)
            ->with(['project', 'assignedUser'])
            ->get();

        // Query Riwayat Laporan dengan Filter
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
            'completedProjects' => $completedProjects, // Data project selesai
            'tasks' => $tasks,
            'dailyReports' => $dailyReports,
            'currentFilter' => $request->filter ?? ''
        ];

        return view('divisions.show', $data);
    }

    // Menghapus laporan harian
    public function destroyReport($id)
    {
        $report = DailyReport::findOrFail($id);
        $division = $report->division;
        $report->delete();

        return redirect()->route('division.show', $division)->with('success', 'Laporan harian berhasil dihapus!');
    }

    // Menyimpan Laporan Harian (Otomatis dari task selesai pada tanggal tersebut)
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

        // Ambil task yang statusnya Completed dan tanggal selesainya (completed_at) tepat pada tanggal laporan
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