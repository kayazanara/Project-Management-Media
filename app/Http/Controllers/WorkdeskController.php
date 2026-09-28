<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Project;
use App\Models\ProjectTask;
use App\Models\ProjectRevision;
use App\Models\User;
use App\Models\Content;
use App\Models\Meeting;
use App\Models\WorkdeskAlert;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;

class WorkdeskController extends Controller
{
    public function index(Request $request)
    {
        $user = Auth::user();

        if (!$user->isManager()) {
            abort(403, 'Workdesk hanya untuk Pembina & Ketua Media.');
        }

        $days = (int) $request->input('days', 30);
        if (!in_array($days, [7, 30, 90])) {
            $days = 30;
        }

        $data = [
            'title' => 'Workdesk',
            'user' => $user,
            'stats' => $this->buildOverviewStats(),
            'actions' => $this->buildActionsForUser($user),
            'alerts' => $this->buildAlerts(),
            'performance' => $this->buildDivisionPerformance(),
            'dailyTrend' => $this->buildDailyCompletionTrend($days),
            'filterDays' => $days,
            'topMembers' => $this->buildTopMembers(),
            'bottomMembers' => $this->buildBottomMembers(),
            'projectHealth' => $this->buildProjectHealth(),
            'upcomingMeetings' => $this->buildUpcomingMeetings(),
            'lastMeeting' => $this->buildLastMeeting(),
            'systemHealth' => $user->isPembina() ? $this->buildSystemHealth() : null,
        ];

        return view('workdesk.index', $data);
    }

    public function getActions()
    {
        $user = Auth::user();
        if (!$user->isManager()) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        return response()->json([
            'actions' => $this->buildActionsForUser($user),
            'timestamp' => now()->toDateTimeString(),
        ]);
    }

    public function getAlerts()
    {
        $user = Auth::user();
        if (!$user->isManager()) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        return response()->json([
            'alerts' => $this->buildAlerts(),
            'timestamp' => now()->toDateTimeString(),
        ]);
    }

    public function getPerformance()
    {
        $user = Auth::user();
        if (!$user->isManager()) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        return response()->json([
            'performance' => $this->buildDivisionPerformance(),
            'timestamp' => now()->toDateTimeString(),
        ]);
    }

    public function markRead($id)
    {
        $alert = WorkdeskAlert::findOrFail($id);

        if ($alert->user_id !== Auth::id()) {
            abort(403);
        }

        $alert->update(['is_read' => true]);

        return response()->json(['success' => true]);
    }

    // ============================================================
    // HELPER: POIN
    // ============================================================

    private function calculatePointsInRange(Carbon $start, Carbon $end): array
    {
        // ⚡ FIX: poin HANYA untuk task berstatus Completed.
        // Sebelumnya semua task yang sudah punya bobot ikut memberi poin
        // (termasuk Pending / In Progress) -> Top Members Workdesk salah.
        $tasks = ProjectTask::where('status', 'Completed')
            ->where(function ($q) use ($start, $end) {
                $q->whereBetween('task_date', [$start->toDateString(), $end->toDateString()])
                  ->orWhere(function ($q2) use ($start, $end) {
                      $q2->whereNull('task_date')
                         ->whereBetween('created_at', [$start, $end]);
                  });
            })
            ->with(['contributors:id', 'assignedUser:id'])
            ->get();

        $result = [];

        foreach ($tasks as $task) {
            $weight = (float) $task->weight;
            if ($weight <= 0) continue;

            $contributorIds = $task->contributors->pluck('id')->toArray();
            if (empty($contributorIds) && $task->assigned_to) {
                $contributorIds = [(int) $task->assigned_to];
            }
            if (empty($contributorIds)) continue;

            $pointPerPerson = $weight / count($contributorIds);

            foreach ($contributorIds as $uid) {
                if (!isset($result[$uid])) {
                    $result[$uid] = ['points' => 0, 'tasks' => 0];
                }
                $result[$uid]['points'] += $pointPerPerson;
                $result[$uid]['tasks'] += 1;
            }
        }

        return $result;
    }

    // ============================================================
    // COLOR MAPPING
    // ============================================================

    private function getDivisionColors(): array
    {
        return [
            'Lapangan'    => ['hex' => '#1cc88a', 'label' => 'Tim Lapangan'],
            'Editing'     => ['hex' => '#224abe', 'label' => 'Tim Editing'],
            'Broadcast'   => ['hex' => '#8b5cf6', 'label' => 'Tim Broadcast'],
            'AI Research' => ['hex' => '#f59f0b', 'label' => 'Tim AI Research'],
        ];
    }

    // ============================================================
    // OVERVIEW
    // ============================================================

    private function buildOverviewStats(): array
    {
        $today = Carbon::today();

        return [
            'total_projects'     => Project::count(),
            'active_projects'    => Project::where('status', '!=', Project::STATUS_COMPLETED)->count(),
            'completed_projects' => Project::where('status', Project::STATUS_COMPLETED)->count(),
            'overdue_projects'   => Project::where('is_overdue', true)
                ->where('status', '!=', Project::STATUS_COMPLETED)
                ->count(),
            'total_tasks'        => ProjectTask::count(),
            'completed_tasks'    => ProjectTask::where('status', 'Completed')->count(),
            'ready_contents'     => Content::where('status', 'Ready')->count(),
            'total_members'      => User::count(),
            'active_members'     => User::where('status', 'Aktif')->count(),
            'tasks_today'        => ProjectTask::whereDate('task_date', $today)->count(),
        ];
    }

    // ============================================================
    // ⚡ FIX: Overdue untuk SEMUA manager (Pembina + Ketua Media)
    // ============================================================

    private function buildActionsForUser(User $user): array
    {
        $actions = [];

        if ($user->isKetuaMedia()) {
            $planningProjects = Project::where('status', Project::STATUS_PLANNING)
                ->orderBy('created_at', 'asc')
                ->get();

            foreach ($planningProjects as $p) {
                $actions[] = [
                    'type' => 'submit_task',
                    'priority' => 'high',
                    'icon' => 'fa-tasks',
                    'color' => 'warning',
                    'title' => 'Susun Task: ' . $p->name,
                    'subtitle' => 'Project baru menunggu task dari Anda',
                    'url' => route('projects.show', $p->id),
                    'cta' => 'Susun Sekarang',
                ];
            }

            $reviewProjects = Project::where('status', Project::STATUS_REVIEW)
                ->whereNull('approved_by_ketua_at')
                ->get();

            foreach ($reviewProjects as $p) {
                $actions[] = [
                    'type' => 'review',
                    'priority' => 'high',
                    'icon' => 'fa-search',
                    'color' => 'info',
                    'title' => 'Review: ' . $p->name,
                    'subtitle' => 'Menunggu persetujuan Anda',
                    'url' => route('projects.show', $p->id),
                    'cta' => 'Review',
                ];
            }
        }

        if ($user->isPembina()) {
            $taskReviewProjects = Project::where('status', Project::STATUS_TASK_REVIEW)
                ->orderBy('created_at', 'asc')
                ->get();

            foreach ($taskReviewProjects as $p) {
                $actions[] = [
                    'type' => 'scoring',
                    'priority' => 'high',
                    'icon' => 'fa-star',
                    'color' => 'primary',
                    'title' => 'Scoring: ' . $p->name,
                    'subtitle' => 'Task sudah disusun, perlu bobot & approve',
                    'url' => route('projects.show', $p->id),
                    'cta' => 'Scoring',
                ];
            }

            $scoringProjects = Project::where('status', Project::STATUS_SCORING)
                ->get();

            foreach ($scoringProjects as $p) {
                $actions[] = [
                    'type' => 'release',
                    'priority' => 'high',
                    'icon' => 'fa-rocket',
                    'color' => 'success',
                    'title' => 'Rilis: ' . $p->name,
                    'subtitle' => 'Scoring selesai, siap dirilis',
                    'url' => route('projects.show', $p->id),
                    'cta' => 'Rilis',
                ];
            }

            $reviewProjects = Project::where('status', Project::STATUS_REVIEW)
                ->whereNull('approved_by_pembina_at')
                ->get();

            foreach ($reviewProjects as $p) {
                $actions[] = [
                    'type' => 'approve',
                    'priority' => 'high',
                    'icon' => 'fa-check-circle',
                    'color' => 'success',
                    'title' => 'Approve: ' . $p->name,
                    'subtitle' => 'Menunggu persetujuan Anda',
                    'url' => route('projects.show', $p->id),
                    'cta' => 'Approve',
                ];
            }
        }

        // ⚡ Overdue untuk SEMUA manager (Pembina + Ketua Media)
        if ($user->isManager()) {
            $overdueProjects = Project::where('is_overdue', true)
                ->where('status', '!=', Project::STATUS_COMPLETED)
                ->get();

            foreach ($overdueProjects as $p) {
                $daysLate = $p->deadline ? round($p->deadline->diffInDays(now())) : 0;

                $actions[] = [
                    'type' => 'overdue',
                    'priority' => 'critical',
                    'icon' => 'fa-exclamation-triangle',
                    'color' => 'danger',
                    'title' => 'Overdue: ' . $p->name,
                    'subtitle' => 'Lewat deadline ' . $daysLate . ' hari',
                    'url' => route('projects.show', $p->id),
                    'cta' => 'Lihat',
                ];
            }
        }

        return $actions;
    }

    // ============================================================
    // ALERTS
    // ============================================================

    private function buildAlerts(): array
    {
        $alerts = [];

        $overdueProjects = Project::where('is_overdue', true)
            ->where('status', '!=', Project::STATUS_COMPLETED)
            ->orderBy('deadline', 'asc')
            ->take(5)
            ->get();

        foreach ($overdueProjects as $p) {
            $daysLate = $p->deadline ? round($p->deadline->diffInDays(now())) : 0;

            $alerts[] = [
                'priority' => 'critical',
                'icon' => 'fa-exclamation-triangle',
                'color' => 'danger',
                'title' => 'Project OVERDUE: ' . $p->name,
                'message' => 'Deadline ' . ($p->deadline ? $p->deadline->format('d M') : '-')
                    . ' (' . $daysLate . ' hari lalu)',
                'url' => route('projects.show', $p->id),
                'cta' => 'Lihat',
            ];
        }

        $atRiskProjects = Project::where('status', '!=', Project::STATUS_COMPLETED)
            ->where('is_overdue', false)
            ->where('deadline', '<=', now()->addDays(3)->toDateString())
            ->where('deadline', '>=', now()->toDateString())
            ->withCount([
                'tasks as total_tasks_count',
                'tasks as completed_tasks_count' => fn($q) => $q->where('status', 'Completed'),
            ])
            ->take(3)
            ->get();

        foreach ($atRiskProjects as $p) {
            $progress = $p->total_tasks_count > 0
                ? round(($p->completed_tasks_count / $p->total_tasks_count) * 100)
                : 0;

            if ($progress < 50) {
                $alerts[] = [
                    'priority' => 'high',
                    'icon' => 'fa-clock',
                    'color' => 'warning',
                    'title' => 'At Risk: ' . $p->name,
                    'message' => 'Progress ' . $progress . '%, deadline ' . ($p->deadline ? $p->deadline->format('d M') : '-'),
                    'url' => route('projects.show', $p->id),
                    'cta' => 'Cek',
                ];
            }
        }

        $inactiveMembers = User::where('status', 'Aktif')
            ->where(function ($q) {
                $q->where('last_login_date', '<', now()->subDays(3)->toDateString())
                  ->orWhereNull('last_login_date');
            })
            ->take(3)
            ->get();

        foreach ($inactiveMembers as $m) {
            $alerts[] = [
                'priority' => 'medium',
                'icon' => 'fa-user-clock',
                'color' => 'info',
                'title' => $m->name . ' tidak aktif',
                'message' => 'Terakhir login: ' . ($m->last_login_date ? $m->last_login_date->format('d M') : 'Belum pernah'),
                'url' => route('profile.show', $m->id),
                'cta' => 'Profil',
            ];
        }

        return $alerts;
    }

    // ============================================================
    // PERFORMANCE
    // ============================================================

    private function buildDivisionPerformance(): array
    {
        $divisions = $this->getDivisionColors();
        $result = [];

        $startOfWeek = now()->startOfWeek();
        $endOfWeek = now()->endOfWeek();
        $prevStart = now()->subWeek()->startOfWeek();
        $prevEnd = now()->subWeek()->endOfWeek();

        foreach ($divisions as $divName => $meta) {
            // ⚡ FIX: hanya anggota berstatus Aktif (sebelumnya semua status ikut dihitung,
            // beda dengan halaman Monitoring yang sudah memfilter Aktif).
            $memberIds = User::where('division', 'like', "%{$divName}%")
                ->where('status', 'Aktif')
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->all();

            if (empty($memberIds)) {
                $result[] = [
                    'name' => $divName,
                    'label' => $meta['label'],
                    'hex' => $meta['hex'],
                    'completion_rate' => 0,
                    'on_time_rate' => 0,
                    'performance_score' => 0,
                    'total_tasks' => 0,
                    'completed_tasks' => 0,
                    'completed_projects' => 0,
                    'trend' => 0,
                ];
                continue;
            }

            // ⚡ FIX: atribusi kerja disamakan dengan sistem poin —
            // pakai KONTRIBUTOR; kalau task belum punya kontributor, pakai PIC (assigned_to).
            // Sebelumnya hanya assigned_to, sehingga bisa beda dengan Top Members.
            $weekTasks = ProjectTask::where(function ($q) use ($memberIds) {
                    $q->whereHas('contributors', fn ($c) => $c->whereIn('users.id', $memberIds))
                      ->orWhere(function ($q2) use ($memberIds) {
                          $q2->whereDoesntHave('contributors')
                             ->whereIn('assigned_to', $memberIds);
                      });
                })
                // ⚡ FIX: SATU basis periode untuk pembilang & penyebut
                // (sebelumnya total pakai task_date OR completed_at, tapi pembilang hanya completed_at)
                ->where(function ($q) use ($startOfWeek, $endOfWeek) {
                    $q->whereBetween('task_date', [$startOfWeek->toDateString(), $endOfWeek->toDateString()])
                      ->orWhere(function ($q2) use ($startOfWeek, $endOfWeek) {
                          $q2->where('status', 'Completed')
                             ->whereBetween('completed_at', [$startOfWeek, $endOfWeek]);
                      });
                })
                ->get();

            $totalTasks     = $weekTasks->count();
            $completedTasks = $weekTasks->where('status', 'Completed')->count();

            // ⚡ FIX: on-time dibandingkan per TANGGAL.
            // Sebelumnya whereColumn('completed_at','<=','task_date') membandingkan
            // datetime dengan date sehingga hasilnya SELALU 0% (semua dianggap telat).
            $onTimeTasks = $weekTasks->filter(function ($t) {
                if ($t->status !== 'Completed' || !$t->task_date || !$t->completed_at) {
                    return false;
                }

                return $t->completed_at->toDateString() <= $t->task_date->toDateString();
            })->count();

            $weightedTotal = (int) $weekTasks->sum('weight');
            $weightedDone  = (int) $weekTasks->where('status', 'Completed')->sum('weight');

            $completedProjects = Project::whereHas('users', function ($q) use ($memberIds) {
                $q->whereIn('users.id', $memberIds);
            })
                ->where('status', Project::STATUS_COMPLETED)
                ->whereBetween('completed_at', [$startOfWeek, $endOfWeek])
                ->count();

            $completionRate = $totalTasks > 0
                ? min(100, round(($completedTasks / $totalTasks) * 100))
                : 0;
            $onTimeRate = $completedTasks > 0
                ? min(100, round(($onTimeTasks / $completedTasks) * 100))
                : 0;
            $weightedRate = $weightedTotal > 0
                ? min(100, round(($weightedDone / $weightedTotal) * 100))
                : 0;

            $performanceScore = round(
                ($completionRate * 0.4) + ($onTimeRate * 0.4) + ($weightedRate * 0.2)
            );

            // ⚡ FIX: atribusi sama (kontributor, fallback PIC) supaya tren minggu lalu sebanding
            $prevCompleted = ProjectTask::where(function ($q) use ($memberIds) {
                    $q->whereHas('contributors', fn ($c) => $c->whereIn('users.id', $memberIds))
                      ->orWhere(function ($q2) use ($memberIds) {
                          $q2->whereDoesntHave('contributors')
                             ->whereIn('assigned_to', $memberIds);
                      });
                })
                ->where('status', 'Completed')
                ->whereBetween('completed_at', [$prevStart, $prevEnd])
                ->count();

            $trend = $prevCompleted > 0
                ? round((($completedTasks - $prevCompleted) / $prevCompleted) * 100)
                : 0;

            $result[] = [
                'name' => $divName,
                'label' => $meta['label'],
                'hex' => $meta['hex'],
                'completion_rate' => $completionRate,
                'on_time_rate' => $onTimeRate,
                'performance_score' => $performanceScore,
                'total_tasks' => $totalTasks,
                'completed_tasks' => $completedTasks,
                'completed_projects' => $completedProjects,
                'trend' => $trend,
            ];
        }

        return $result;
    }

    private function buildDailyCompletionTrend(int $days = 30): array
    {
        $divisions = $this->getDivisionColors();
        $end = now();
        $start = $end->copy()->subDays($days - 1);

        $dates = [];
        $current = $start->copy();
        while ($current <= $end) {
            $dates[] = $current->format('Y-m-d');
            $current->addDay();
        }

        $result = [
            'labels' => array_map(fn($d) => Carbon::parse($d)->format('d M'), $dates),
            'datasets' => [],
            'days' => $days,
        ];

        foreach ($divisions as $divName => $meta) {
            // ⚡ FIX: hanya anggota berstatus Aktif (konsisten dengan performa divisi & Monitoring)
            $memberIds = User::where('division', 'like', "%{$divName}%")
                ->where('status', 'Aktif')
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->all();

            if (empty($memberIds)) {
                $result['datasets'][] = [
                    'label' => $meta['label'],
                    'hex' => $meta['hex'],
                    'data' => array_fill(0, count($dates), 0),
                ];
                continue;
            }

            // ⚡ FIX: atribusi sama dengan sistem poin (kontributor, fallback PIC)
            $totalTasks = ProjectTask::where(function ($q) use ($memberIds) {
                    $q->whereHas('contributors', fn ($c) => $c->whereIn('users.id', $memberIds))
                      ->orWhere(function ($q2) use ($memberIds) {
                          $q2->whereDoesntHave('contributors')
                             ->whereIn('assigned_to', $memberIds);
                      });
                })->count();

            $data = [];
            foreach ($dates as $date) {
                if ($totalTasks == 0) {
                    $data[] = 0;
                    continue;
                }

                $completed = ProjectTask::where(function ($q) use ($memberIds) {
                        $q->whereHas('contributors', fn ($c) => $c->whereIn('users.id', $memberIds))
                          ->orWhere(function ($q2) use ($memberIds) {
                              $q2->whereDoesntHave('contributors')
                                 ->whereIn('assigned_to', $memberIds);
                          });
                    })
                    ->where('status', 'Completed')
                    ->whereDate('completed_at', '<=', $date)
                    ->count();

                $data[] = round(($completed / $totalTasks) * 100, 1);
            }

            $result['datasets'][] = [
                'label' => $meta['label'],
                'hex' => $meta['hex'],
                'data' => $data,
            ];
        }

        return $result;
    }

    // ============================================================
    // TOP / BOTTOM MEMBERS
    // ============================================================

    private function buildTopMembers(): array
    {
        $start = now()->startOfWeek();
        $end = now()->endOfWeek();

        $pointsData = $this->calculatePointsInRange($start, $end);

        if (empty($pointsData)) {
            return [];
        }

        return User::whereIn('id', array_keys($pointsData))
            ->get()
            ->map(function ($u) use ($pointsData) {
                return [
                    'id' => $u->id,
                    'name' => $u->name,
                    'avatar' => $u->avatar,
                    'division' => $u->division ?? 'Tanpa Divisi',
                    'points' => round($pointsData[$u->id]['points']),
                    'tasks_count' => $pointsData[$u->id]['tasks'],
                ];
            })
            ->sortByDesc('points')
            ->take(5)
            ->values()
            ->toArray();
    }

    private function buildBottomMembers(): array
    {
        $start = now()->startOfWeek();
        $end = now()->endOfWeek();

        $pointsData = $this->calculatePointsInRange($start, $end);

        return User::where('status', 'Aktif')
            ->get()
            ->map(function ($u) use ($pointsData) {
                $points = isset($pointsData[$u->id]) ? round($pointsData[$u->id]['points']) : 0;
                $tasks = $pointsData[$u->id]['tasks'] ?? 0;

                return [
                    'id' => $u->id,
                    'name' => $u->name,
                    'avatar' => $u->avatar,
                    'division' => $u->division ?? 'Tanpa Divisi',
                    'points' => $points,
                    'tasks_count' => $tasks,
                    'last_login' => $u->last_login_date ? $u->last_login_date->format('d M') : 'Belum pernah',
                ];
            })
            ->filter(fn($u) => $u['points'] < 3)
            ->sortBy('points')
            ->take(5)
            ->values()
            ->toArray();
    }

    // ============================================================
    // PROJECT HEALTH
    // ============================================================

    private function buildProjectHealth(): array
    {
        $activeProjects = Project::where('status', '!=', Project::STATUS_COMPLETED)
            ->withCount([
                'tasks as total_tasks_count',
                'tasks as completed_tasks_count' => fn($q) => $q->where('status', 'Completed'),
            ])
            ->get();

        $onTrack = [];
        $atRisk = [];
        $overdue = [];

        foreach ($activeProjects as $p) {
            $progress = $p->total_tasks_count > 0
                ? round(($p->completed_tasks_count / $p->total_tasks_count) * 100)
                : 0;

            $data = [
                'id' => $p->id,
                'name' => $p->name,
                'progress' => $progress,
                'deadline' => $p->deadline ? $p->deadline->format('d M Y') : '-',
                'status' => $p->status,
                'days_left' => $p->deadline ? (int) round(now()->diffInDays($p->deadline, false)) : null,
            ];

            // ⚡ FIX: at-risk = deadline <= 3 hari lagi DAN belum terlewat.
            // Sebelumnya tandanya terbalik ($p->deadline->diffInDays(now(), false) <= 3) sehingga
            // SEMUA project berdeadline masih jauh ikut dicap "At Risk" (mis. sisa 26 hari, progress 0%).
            $daysLeft = $data['days_left'];

            if ($p->is_overdue) {
                $overdue[] = $data;
            } elseif ($daysLeft !== null && $daysLeft >= 0 && $daysLeft <= 3 && $progress < 50) {
                $atRisk[] = $data;
            } else {
                $onTrack[] = $data;
            }
        }

        return [
            'on_track' => array_slice($onTrack, 0, 5),
            'at_risk' => array_slice($atRisk, 0, 5),
            'overdue' => array_slice($overdue, 0, 5),
            'counts' => [
                'on_track' => count($onTrack),
                'at_risk' => count($atRisk),
                'overdue' => count($overdue),
            ],
        ];
    }

    // ============================================================
    // MEETINGS
    // ============================================================

    private function buildUpcomingMeetings(): array
    {
        if (!class_exists(Meeting::class)) return [];

        return Meeting::upcoming(7)->take(3)->get()->map(function ($m) {
            return [
                'id' => $m->id,
                'title' => $m->title,
                'date' => $m->date_formatted,
                'time' => substr($m->start_time, 0, 5) . ' - ' . substr($m->end_time, 0, 5),
                'location' => $m->location ?? '-',
                'attendee_count' => count($m->attendees ?? []),
            ];
        })->toArray();
    }

    private function buildLastMeeting(): ?array
    {
        if (!class_exists(Meeting::class)) return null;

        $last = Meeting::published()->orderBy('meeting_date', 'desc')->first();
        if (!$last) return null;

        return [
            'id' => $last->id,
            'title' => $last->title,
            'date' => $last->date_formatted,
            'total_decisions' => count($last->decisions ?? []),
            'total_actions' => $last->total_action_items,
            'completed_actions' => $last->completed_action_items,
        ];
    }

    // ============================================================
    // SYSTEM HEALTH
    // ============================================================

    private function buildSystemHealth(): array
    {
        $storageFree = @disk_free_space(storage_path());
        $storageTotal = @disk_total_space(storage_path());
        $storagePercent = ($storageTotal > 0) ? round((1 - ($storageFree / $storageTotal)) * 100) : 0;

        $queueCount = \DB::table('jobs')->count();
        $failedJobs = \DB::table('failed_jobs')->count();

        $wahaUrl = rtrim(config('services.waha.url'), '/');
        $wahaKey = config('services.waha.api_key');
        $wahaSession = config('services.waha.session', 'default');

        // ⚡ FIX: hormati WAHA_ENABLED. Sebelumnya panel System Health SELALU menembak
        // HTTP ke WAHA (timeout 3 detik) walau fitur WA dimatikan -> setiap kali Pembina
        // membuka Workdesk halaman menunggu ~4 detik. Kalau dimatikan: lewati total.
        if (! (bool) config('services.waha.enabled', true)) {
            return [
                'storage_percent' => $storagePercent,
                'queue_pending' => $queueCount,
                'queue_failed' => $failedJobs,
                'waha_status' => 'disabled',
            ];
        }

        $wahaStatus = 'offline';

        try {
            $response = \Http::timeout(3)
                ->withHeaders(['X-Api-Key' => $wahaKey])
                ->get("{$wahaUrl}/api/sessions");

            if ($response->successful()) {
                $sessions = $response->json();

                $matchedSession = null;
                if (is_array($sessions)) {
                    foreach ($sessions as $s) {
                        if (($s['name'] ?? null) === $wahaSession) {
                            $matchedSession = $s;
                            break;
                        }
                    }
                }

                if (!$matchedSession) {
                    $wahaStatus = 'no_session';
                } else {
                    $sessionStatus = strtoupper($matchedSession['status'] ?? 'UNKNOWN');
                    $wahaStatus = match ($sessionStatus) {
                        'WORKING'       => 'connected',
                        'SCAN_QR_CODE'  => 'need_qr',
                        'STARTING'      => 'starting',
                        'STOPPED', 'FAILED' => 'stopped',
                        default         => 'error',
                    };
                }
            } else {
                $wahaStatus = match ($response->status()) {
                    401, 403 => 'unauthorized',
                    default  => 'error',
                };
            }
        } catch (\Throwable $e) {
            $wahaStatus = 'offline';
        }

        return [
            'storage_percent' => $storagePercent,
            'queue_pending' => $queueCount,
            'queue_failed' => $failedJobs,
            'waha_status' => $wahaStatus,
        ];
    }
}