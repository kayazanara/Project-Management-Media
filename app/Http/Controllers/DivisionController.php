<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Project;
use App\Models\ProjectTask;
use App\Models\DailyReport;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class DivisionController extends Controller
{
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

    public function show(Request $request, $divisionName)
    {
        $decodedDivision = urldecode($divisionName);
        $keyword = trim(str_replace('Tim', '', $decodedDivision));

        $members = User::where('division', 'like', '%' . $keyword . '%')->get();
        $memberIds = $members->pluck('id');

        // === Project Aktif ===
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

        // === Project Selesai (dengan filter bulan/tahun) ===
        $completedQuery = Project::whereHas('users', function ($q) use ($memberIds) {
            $q->whereIn('users.id', $memberIds);
        })
            ->where('status', 'Completed')
            ->with(['users', 'tasks.assignedUser']);

        $filterMonth = $request->input('completed_month');
        $filterYear  = $request->input('completed_year');

        if ($filterMonth) {
            $completedQuery->whereMonth('completed_at', $filterMonth);
        }
        if ($filterYear) {
            $completedQuery->whereYear('completed_at', $filterYear);
        }

        $completedProjects = $completedQuery->orderBy('completed_at', 'desc')->get();

        // === Tasks ===
        $activeProjectIds = $projects->pluck('id');
        $tasks = ProjectTask::whereIn('project_id', $activeProjectIds)
            ->with(['project.users', 'assignedUser', 'contributors'])
            ->orderByRaw("CASE WHEN status = 'Completed' THEN 1 ELSE 0 END")
            ->orderBy('task_date', 'asc')
            ->get();

        // === Daily Reports ===
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

        // === Daftar tahun tersedia untuk filter ===
        // ⭐ FIX: pakai DB::table() untuk bypass global scope di Model Project
        $availableYears = DB::table('projects')
            ->where('status', 'Completed')
            ->whereNotNull('completed_at')
            ->pluck('completed_at')
            ->map(fn($date) => (int) Carbon::parse($date)->format('Y'))
            ->unique()
            ->sortDesc()
            ->values()
            ->toArray();

        $data = [
            'title' => 'Monitoring Divisi: ' . $decodedDivision,
            'divisionName' => $decodedDivision,
            'members' => $members,
            'projects' => $projects,
            'completedProjects' => $completedProjects,
            'tasks' => $tasks,
            'dailyReports' => $dailyReports,
            'currentFilter' => $request->filter ?? '',
            'filterMonth' => $filterMonth,
            'filterYear' => $filterYear,
            'availableYears' => $availableYears,
        ];

        return view('divisions.show', $data);
    }

    public function updateProjectStatus(Request $request, $divisionName, $id)
    {
        $request->validate([
            'status' => 'required|string|in:Planning,On Progress,Review,Revision,Completed,Overdue',
        ]);

        $project = Project::findOrFail($id);
        $user = auth()->user();
        $isManager = $this->isManager($user);
        $newStatus = $request->status;
        $oldStatus = $project->status;
        $decodedDivision = urldecode($divisionName);

        if ($oldStatus === 'Completed') {
            return redirect()->route('division.show', $decodedDivision)
                ->with('error', 'Project sudah selesai dan tidak bisa diubah lagi.');
        }

        if ($oldStatus === 'Overdue') {
            if (!$isManager) {
                return redirect()->route('division.show', $decodedDivision)
                    ->with('error', 'Project Overdue hanya bisa diubah oleh Pembina/Ketua Media.');
            }
            if ($newStatus !== 'Completed') {
                return redirect()->route('division.show', $decodedDivision)
                    ->with('error', 'Project Overdue hanya bisa diubah ke Completed.');
            }
        }

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
                return redirect()->route('division.show', $decodedDivision)
                    ->with('error', "Anda tidak punya akses untuk mengubah status dari {$oldStatus} ke {$newStatus}.");
            }
        }

        // Set completed_at kalau Completed, reset kalau bukan
        $updateData = ['status' => $newStatus];
        if ($newStatus === 'Completed') {
            $updateData['completed_at'] = now();
        } else {
            $updateData['completed_at'] = null;
        }

        $project->update($updateData);

        return redirect()->route('division.show', $decodedDivision)
            ->with('success', 'Status project "' . $project->name . '" berhasil diubah menjadi ' . $newStatus . '!');
    }

    public function destroyReport($id)
    {
        // ⚡ AUDIT (D4): route laporan divisi ada di luar grup middleware dan method ini
        // tidak punya cek hak akses -> siapa pun bisa menghapus laporan apa pun.
        if (! auth()->user()->isManager() && ! auth()->user()->isKepalaDivisi()) {
            abort(403, 'Anda tidak berhak menghapus laporan divisi.');
        }

        $report = DailyReport::findOrFail($id);
        $division = $report->division;
        $report->delete();

        return redirect()->route('division.show', $division)->with('success', 'Laporan harian berhasil dihapus!');
    }

    public function storeReport(Request $request, $divisionName)
    {
        // ⚡ AUDIT (D4): route di luar grup middleware & tanpa cek hak akses -> siapa pun
        // bisa menambah laporan di divisi mana pun.
        $user = auth()->user();
        $namaDivisi = strtolower(trim(str_replace('Tim', '', urldecode($divisionName))));

        if (! $user->isManager()
            && ! ($user->isKepalaDivisi() && str_contains(strtolower($user->division ?? ''), $namaDivisi))) {
            abort(403, 'Anda tidak berhak menambah laporan di divisi ini.');
        }

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