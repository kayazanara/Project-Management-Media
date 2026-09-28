<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Project;
use App\Models\ProjectTask;
use App\Models\User;
use App\Models\Content;
use App\Models\Meeting;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;

class MonitoringController extends Controller
{
    /**
     * ⚡ FASE 2: ambang status Workload Balance berdasarkan TOTAL POIN periode terpilih.
     */
    const OVERLOAD_POINTS = 65;      // >= 65 poin  -> Overload
    const BALANCED_MIN_POINTS = 20;  // 20-64 poin  -> Seimbang, di bawahnya Ringan

    public function index(Request $request)
    {
        $user = Auth::user();

        if (!$user->isPembina()) {
            abort(403, 'Menu Monitoring Tim hanya untuk Pembina Media.');
        }

        $days = (int) $request->input('days', 30);
        if (!in_array($days, [7, 30, 90])) {
            $days = 30;
        }

        $start = now()->subDays($days - 1)->startOfDay();
        $end = now()->endOfDay();

        return view('monitoring.index', [
            'title'           => 'Monitoring Tim',
            'filterDays'      => $days,
            'startDate'       => $start,
            'endDate'         => $end,
            'members'         => $this->buildMemberPerformance($start, $end),
            'monthlyTrend'    => $this->buildMonthlyTrend(),
            // ⚡ FIX: kirim periode supaya kartu Workload Balance ikut filter 7/30/90 hari
            // (sebelumnya dipanggil tanpa periode sehingga hasilnya identik untuk semua filter)
            'workload'        => $this->buildWorkloadBalance($start, $end),
            'overloadPoints'  => self::OVERLOAD_POINTS,
            'balancedMin'     => self::BALANCED_MIN_POINTS,
            'deadline'        => $this->buildDeadlineAdherence($start, $end),
            'alerts'          => $this->buildAlerts(),
            'divisionSummary' => $this->buildDivisionSummary($start, $end),
            'directory'       => $this->buildTeamDirectory($start, $end),
        ]);
    }

    // ============================================================
    // HELPER: KETEPATAN WAKTU
    // ============================================================

    /**
     * Apakah task selesai tepat waktu (atau lebih awal)?
     *
     * ⚡ FIX: bandingkan TANGGAL-nya saja. Sebelumnya semua tempat memakai
     * $completedAt <= $taskDate, yaitu membandingkan datetime (mis. 06:00) dengan
     * tanggal (00:00) sehingga task yang selesai di HARI YANG SAMA selalu dianggap
     * telat -> on-time rate selalu 0%.
     */
    private function isOnTime(ProjectTask $task): bool
    {
        if (! $task->task_date || ! $task->completed_at) {
            return false;
        }

        return $task->completed_at->toDateString() <= $task->task_date->toDateString();
    }

    // ============================================================
    // TEAM DIRECTORY
    // ============================================================

    private function buildTeamDirectory(Carbon $start, Carbon $end): array
    {
        $users = User::whereIn('division_role', ['ketua_media', 'kepala_divisi', 'anggota'])
            ->where('status', 'Aktif')
            ->orderBy('name')
            ->get();

        $result = [];

        foreach ($users as $u) {
            $points = 0;
            $taskCount = 0;
            $completedCount = 0;
            $onTimeCount = 0;
            $lateCount = 0;
            $lateDaysSum = 0;

            $tasks = ProjectTask::where(function ($q) use ($start, $end) {
                    $q->whereBetween('task_date', [$start->toDateString(), $end->toDateString()])
                      ->orWhere(function ($q2) use ($start, $end) {
                          $q2->whereNull('task_date')
                             ->whereBetween('created_at', [$start, $end]);
                      });
                })
                ->whereHas('contributors', function ($q) use ($u) {
                    $q->where('users.id', $u->id);
                })
                ->with(['contributors:id', 'assignedUser:id'])
                ->get();

            foreach ($tasks as $task) {
                $contributorIds = $task->contributors->pluck('id')->toArray();
                if (empty($contributorIds) && $task->assigned_to) {
                    $contributorIds = [(int) $task->assigned_to];
                }
                if (empty($contributorIds)) continue;

                if (in_array($u->id, $contributorIds)) {
                    $taskCount++;

                    // ⚡ FIX: poin HANYA untuk task yang benar-benar SELESAI.
                    // Sebelumnya poin diberikan begitu bobot diisi, walau task masih Pending.
                    if ($task->status === 'Completed') {
                        $points += ((float) $task->weight) / count($contributorIds);
                    }

                    if ($task->status === 'Completed' && $task->task_date && $task->completed_at) {
                        $completedCount++;

                        // ⚡ FIX: pakai helper isOnTime() -> perbandingan per TANGGAL
                        if ($this->isOnTime($task)) {
                            $onTimeCount++;
                        } else {
                            $lateCount++;
                            $lateDaysSum += $task->task_date->diffInDays($task->completed_at);
                        }
                    }
                }
            }

            $onTimeRate = $completedCount > 0 ? round(($onTimeCount / $completedCount) * 100) : 0;
            $avgLateDays = $lateCount > 0 ? round($lateDaysSum / $lateCount, 1) : 0;

            $completedProjects = $u->projects()->where('status', 'Completed')->count();
            $activeProjects = $u->projects()->where('status', '!=', 'Completed')->count();

            $roleLabel = match ($u->division_role) {
                'ketua_media'   => 'Ketua Media',
                'kepala_divisi' => 'Kepala Divisi',
                'anggota'       => 'Anggota',
                'pembina'       => 'Pembina',
                default         => 'Anggota',
            };

            $divisionLabel = $u->division ?? '-';
            $jabatan = ($u->division_role === 'ketua_media' || $u->division_role === 'pembina')
                ? $roleLabel
                : ($roleLabel . ' • ' . $divisionLabel);

            $trend = $this->getUserMonthlyTrend($u);
            $trendLabels = $trend['labels'];
            $trendData = $trend['data'];

            $recentTasks = ProjectTask::where(function ($q) use ($u) {
                    $q->where('assigned_to', $u->id)
                      ->orWhereHas('contributors', function ($q2) use ($u) {
                          $q2->where('users.id', $u->id);
                      });
                })
                ->with('project:id,name')
                ->orderBy('updated_at', 'desc')
                ->take(5)
                ->get()
                ->map(function ($t) {
                    return [
                        'title'        => $t->title,
                        'status'       => $t->status,
                        'task_date'    => $t->task_date ? Carbon::parse($t->task_date)->format('d M Y') : '-',
                        'project_name' => $t->project->name ?? '-',
                    ];
                })
                ->toArray();

            $activities = [];

            $completedTasks = ProjectTask::where('status', 'Completed')
                ->where('completed_at', '>=', now()->subDays(30))
                ->whereHas('contributors', function ($q) use ($u) {
                    $q->where('users.id', $u->id);
                })
                ->with('project:id,name')
                ->orderBy('completed_at', 'desc')
                ->take(8)
                ->get();

            foreach ($completedTasks as $t) {
                $activities[] = [
                    'date'        => $t->completed_at,
                    'icon'        => 'fa-check-circle',
                    'color'       => 'success',
                    'description' => 'Menyelesaikan task "' . $t->title . '"',
                    'context'     => $t->project->name ?? '-',
                ];
            }

            $completedProjs = Project::whereHas('users', function ($q) use ($u) {
                    $q->where('users.id', $u->id);
                })
                ->where('status', 'Completed')
                ->where('completed_at', '>=', now()->subDays(30))
                ->orderBy('completed_at', 'desc')
                ->take(4)
                ->get();

            foreach ($completedProjs as $p) {
                $activities[] = [
                    'date'        => $p->completed_at,
                    'icon'        => 'fa-trophy',
                    'color'       => 'warning',
                    'description' => 'Project "' . $p->name . '" selesai',
                    'context'     => 'Project',
                ];
            }

            usort($activities, fn($a, $b) => $b['date'] <=> $a['date']);
            $activities = array_slice($activities, 0, 10);

            $activitiesFormatted = array_map(function ($act) {
                return [
                    'date'        => Carbon::parse($act['date'])->translatedFormat('d M Y H:i'),
                    'icon'        => $act['icon'],
                    'color'       => $act['color'],
                    'description' => $act['description'],
                    'context'     => $act['context'],
                ];
            }, $activities);

            $themeColors = [
                'blue-purple' => ['bg' => 'linear-gradient(135deg, #4e73df 0%, #8b5cf6 100%)', 'solid' => '#4e73df'],
                'ocean'       => ['bg' => 'linear-gradient(135deg, #06b6d4 0%, #4e73df 100%)', 'solid' => '#06b6d4'],
                'sunset'      => ['bg' => 'linear-gradient(135deg, #f59f0b 0%, #d9480f 100%)', 'solid' => '#f59f0b'],
                'emerald'     => ['bg' => 'linear-gradient(135deg, #1cc88a 0%, #16a34a 100%)', 'solid' => '#1cc88a'],
                'rose'        => ['bg' => 'linear-gradient(135deg, #f43f5e 0%, #a855f7 100%)', 'solid' => '#f43f5e'],
                'dark'        => ['bg' => 'linear-gradient(135deg, #2d3748 0%, #1a202c 100%)', 'solid' => '#2d3748'],
            ];
            $theme = $themeColors[$u->theme_color ?? 'blue-purple'] ?? $themeColors['blue-purple'];

            $result[] = [
                'id'                 => $u->id,
                'name'               => $u->name,
                'avatar'             => $u->avatar ? asset('storage/avatars/' . $u->avatar) : null,
                'background_image'   => $u->background_image ? asset('storage/backgrounds/' . $u->background_image) : null,
                'initials'           => strtoupper(substr($u->name, 0, 2)),
                'jabatan'            => $jabatan,
                'role_label'         => $roleLabel,
                'division'           => $divisionLabel,
                'division_role'      => $u->division_role,
                'theme_gradient'     => $theme['bg'],
                'theme_solid'        => $theme['solid'],
                'points'             => round($points),
                'total_tasks'        => $taskCount,
                'completed_tasks'    => $completedCount,
                'on_time_count'      => $onTimeCount,
                'late_count'         => $lateCount,
                'on_time_rate'       => $onTimeRate,
                'avg_late_days'      => $avgLateDays,
                'completed_projects' => $completedProjects,
                'active_projects'    => $activeProjects,
                'last_login'         => $u->last_login_date ? $u->last_login_date->translatedFormat('d M Y') : 'Belum pernah',
                'last_login_days'    => $u->last_login_date ? $u->last_login_date->diffInDays(now()) : null,
                'trend_labels'       => $trendLabels,
                'trend_data'         => $trendData,
                'recent_tasks'       => $recentTasks,
                'recent_activities'  => $activitiesFormatted,
                'profile_url'        => route('profile.show', $u->id),
            ];
        }

        usort($result, fn($a, $b) => $b['points'] <=> $a['points']);

        foreach ($result as $i => &$r) {
            $r['rank'] = $i + 1;
        }
        unset($r);

        return $result;
    }

    private function getUserMonthlyTrend(User $u): array
    {
        $labels = [];
        $data = [];

        for ($i = 5; $i >= 0; $i--) {
            $month = now()->subMonths($i);
            $labels[] = $month->translatedFormat('M Y');

            $start = $month->copy()->startOfMonth();
            $end = $month->copy()->endOfMonth();

            $tasks = ProjectTask::where(function ($q) use ($start, $end) {
                    $q->whereBetween('task_date', [$start->toDateString(), $end->toDateString()])
                      ->orWhere(function ($q2) use ($start, $end) {
                          $q2->whereNull('task_date')
                             ->whereBetween('created_at', [$start, $end]);
                      });
                })
                ->whereHas('contributors', function ($q) use ($u) {
                    $q->where('users.id', $u->id);
                })
                ->with(['contributors:id', 'assignedUser:id'])
                ->get();

            $points = 0;
            foreach ($tasks as $task) {
                $contributorIds = $task->contributors->pluck('id')->toArray();
                if (empty($contributorIds) && $task->assigned_to) {
                    $contributorIds = [(int) $task->assigned_to];
                }
                if (empty($contributorIds)) continue;

                // ⚡ FIX: poin HANYA untuk task yang benar-benar SELESAI
                if (in_array($u->id, $contributorIds) && $task->status === 'Completed') {
                    $points += ((float) $task->weight) / count($contributorIds);
                }
            }

            $data[] = round($points);
        }

        return ['labels' => $labels, 'data' => $data];
    }

    // ============================================================
    // PERFORMA PER ANGGOTA
    // ============================================================

    private function buildMemberPerformance(Carbon $start, Carbon $end): array
    {
        $users = User::whereIn('division_role', ['ketua_media', 'kepala_divisi', 'anggota'])
            ->where('status', 'Aktif')
            ->orderBy('name')
            ->get();

        $result = [];

        foreach ($users as $user) {
            $tasks = ProjectTask::where(function ($q) use ($start, $end) {
                    $q->whereBetween('task_date', [$start->toDateString(), $end->toDateString()])
                      ->orWhere(function ($q2) use ($start, $end) {
                          $q2->whereNull('task_date')
                             ->whereBetween('created_at', [$start, $end]);
                      });
                })
                ->whereHas('contributors', function ($q) use ($user) {
                    $q->where('users.id', $user->id);
                })
                ->with(['contributors:id', 'assignedUser:id'])
                ->get();

            $points = 0;
            $taskCount = 0;
            $completedCount = 0;
            $onTimeCount = 0;
            $lateCount = 0;

            foreach ($tasks as $task) {
                $contributorIds = $task->contributors->pluck('id')->toArray();
                if (empty($contributorIds) && $task->assigned_to) {
                    $contributorIds = [(int) $task->assigned_to];
                }
                if (empty($contributorIds)) continue;

                if (in_array($user->id, $contributorIds)) {
                    $taskCount++;

                    // ⚡ FIX: poin HANYA untuk task yang benar-benar SELESAI
                    // (sebelumnya poin diberikan begitu bobot diisi, walau task masih Pending)
                    if ($task->status === 'Completed') {
                        $points += ((float) $task->weight) / count($contributorIds);
                    }

                    if ($task->status === 'Completed' && $task->task_date && $task->completed_at) {
                        $completedCount++;

                        // ⚡ FIX: pakai helper isOnTime() -> perbandingan per TANGGAL
                        if ($this->isOnTime($task)) {
                            $onTimeCount++;
                        } else {
                            $lateCount++;
                        }
                    }
                }
            }

            $onTimeRate = $completedCount > 0 ? round(($onTimeCount / $completedCount) * 100) : 0;

            $result[] = [
                'id'              => $user->id,
                'name'            => $user->name,
                'avatar'          => $user->avatar,
                'division'        => $user->division ?? '-',
                'division_role'   => $user->division_role ?? 'anggota',
                'points'          => round($points),
                'total_tasks'     => $taskCount,
                'completed_tasks' => $completedCount,
                'on_time_count'   => $onTimeCount,
                'late_count'      => $lateCount,
                'on_time_rate'    => $onTimeRate,
                'last_login'      => $user->last_login_date ? $user->last_login_date->format('d M Y') : 'Belum pernah',
            ];
        }

        usort($result, fn($a, $b) => $b['points'] <=> $a['points']);

        return $result;
    }

    // ============================================================
    // TREND BULANAN (6 bulan)
    // ============================================================

    private function buildMonthlyTrend(): array
    {
        $months = [];
        $labels = [];

        for ($i = 5; $i >= 0; $i--) {
            $month = now()->subMonths($i);
            $labels[] = $month->translatedFormat('M Y');
            $months[] = [
                'start' => $month->copy()->startOfMonth(),
                'end'   => $month->copy()->endOfMonth(),
            ];
        }

        $users = User::whereIn('division_role', ['ketua_media', 'kepala_divisi', 'anggota'])
            ->where('status', 'Aktif')
            ->orderBy('name')
            ->get();

        $datasets = [];
        $colors = ['#4e73df', '#1cc88a', '#36b9cc', '#f6c23e', '#e74a3b', '#8b5cf6', '#f59f0b', '#224abe'];

        foreach ($users as $user) {
            $userPoints = [];

            foreach ($months as $m) {
                $tasks = ProjectTask::where(function ($q) use ($m) {
                        $q->whereBetween('task_date', [$m['start']->toDateString(), $m['end']->toDateString()])
                          ->orWhere(function ($q2) use ($m) {
                              $q2->whereNull('task_date')
                                 ->whereBetween('created_at', [$m['start'], $m['end']]);
                          });
                    })
                    ->whereHas('contributors', function ($q) use ($user) {
                        $q->where('users.id', $user->id);
                    })
                    ->with(['contributors:id', 'assignedUser:id'])
                    ->get();

                $points = 0;
                foreach ($tasks as $task) {
                    $contributorIds = $task->contributors->pluck('id')->toArray();
                    if (empty($contributorIds) && $task->assigned_to) {
                        $contributorIds = [(int) $task->assigned_to];
                    }
                    if (empty($contributorIds)) continue;

                    // ⚡ FIX: poin HANYA untuk task yang benar-benar SELESAI
                    if (in_array($user->id, $contributorIds) && $task->status === 'Completed') {
                        $points += ((float) $task->weight) / count($contributorIds);
                    }
                }

                $userPoints[] = round($points);
            }

            if (array_sum($userPoints) === 0) continue;

            $color = $colors[count($datasets) % count($colors)];

            $datasets[] = [
                'label'           => $user->name,
                'data'            => $userPoints,
                'borderColor'     => $color,
                'backgroundColor' => $color . '20',
            ];
        }

        return [
            'labels'   => $labels,
            'datasets' => $datasets,
        ];
    }

    // ============================================================
    // WORKLOAD BALANCE
    // ============================================================

    private function buildWorkloadBalance(Carbon $start, Carbon $end): array
    {
        $users = User::whereIn('division_role', ['ketua_media', 'kepala_divisi', 'anggota'])
            ->where('status', 'Aktif')
            ->orderBy('name')
            ->get();

        $result = [];

        foreach ($users as $user) {
            // ⚡ FIX (M2): (1) dibatasi PERIODE terpilih supaya filter 7/30/90 hari berpengaruh,
            // (2) atribusi pakai KONTRIBUTOR (fallback PIC) supaya konsisten dengan metrik poin.
            $tasks = ProjectTask::where(function ($q) use ($start, $end) {
                    $q->whereBetween('task_date', [$start->toDateString(), $end->toDateString()])
                      ->orWhere(function ($q2) use ($start, $end) {
                          $q2->whereNull('task_date')
                             ->whereBetween('created_at', [$start, $end]);
                      });
                })
                ->where(function ($q) use ($user) {
                    $q->whereHas('contributors', fn ($c) => $c->where('users.id', $user->id))
                      ->orWhere(function ($q2) use ($user) {
                          $q2->whereDoesntHave('contributors')
                             ->where('assigned_to', $user->id);
                      });
                })
                ->with(['contributors:id'])
                ->get();

            $activeTasks    = $tasks->where('status', '!=', 'Completed')->count();
            $completedTasks = $tasks->where('status', 'Completed')->count();
            $totalTasks     = $tasks->count();
            $activeRatio = $totalTasks > 0 ? round(($activeTasks / $totalTasks) * 100) : 0;

            // ⚡ FASE 2:
            //   $points      = total poin periode ini (HANYA task selesai, dibagi rata ke kontributor)
            //   $plannedLoad = estimasi beban = total bobot task yang DIJADWALKAN di periode ini
            //                  (termasuk yang belum selesai)
            $points = 0;
            $plannedLoad = 0;

            foreach ($tasks as $task) {
                $contributorIds = $task->contributors->pluck('id')->toArray();
                if (empty($contributorIds) && $task->assigned_to) {
                    $contributorIds = [(int) $task->assigned_to];
                }
                if (empty($contributorIds)) {
                    continue;
                }

                $plannedLoad += (float) $task->weight;

                if ($task->status === 'Completed') {
                    $points += ((float) $task->weight) / count($contributorIds);
                }
            }

            // ⚡ FASE 2: status ditentukan dari TOTAL POIN periode ini (bukan rasio task aktif).
            if ($totalTasks === 0) {
                $status = 'idle'; $statusLabel = 'Idle'; $statusColor = 'secondary'; $statusIcon = 'fa-circle';
            } elseif ($points >= self::OVERLOAD_POINTS) {
                $status = 'overload'; $statusLabel = 'Overload'; $statusColor = 'danger'; $statusIcon = 'fa-exclamation-triangle';
            } elseif ($points >= self::BALANCED_MIN_POINTS) {
                $status = 'balanced'; $statusLabel = 'Seimbang'; $statusColor = 'success'; $statusIcon = 'fa-check-circle';
            } else {
                $status = 'light'; $statusLabel = 'Ringan'; $statusColor = 'info'; $statusIcon = 'fa-info-circle';
            }

            $result[] = [
                'id'              => $user->id,
                'name'            => $user->name,
                'avatar'          => $user->avatar,
                'division'        => $user->division ?? '-',
                'division_role'   => $user->division_role ?? 'anggota',
                'active_tasks'    => $activeTasks,
                'completed_tasks' => $completedTasks,
                'total_tasks'     => $totalTasks,
                'active_ratio'    => $activeRatio,
                'points'          => (int) round($points),
                'planned_load'    => (int) round($plannedLoad),
                'status'          => $status,
                'status_label'    => $statusLabel,
                'status_color'    => $statusColor,
                'status_icon'     => $statusIcon,
            ];
        }

        // ⚡ FASE 2: Overload paling atas, lalu poin terbesar
        usort($result, function ($a, $b) {
            $order = ['overload' => 1, 'balanced' => 2, 'light' => 3, 'idle' => 4];

            $statusCompare = ($order[$a['status']] ?? 99) <=> ($order[$b['status']] ?? 99);

            return $statusCompare !== 0 ? $statusCompare : ($b['points'] <=> $a['points']);
        });

        return $result;
    }

    // ============================================================
    // DEADLINE ADHERENCE
    // ============================================================

    private function buildDeadlineAdherence(Carbon $start, Carbon $end): array
    {
        $users = User::whereIn('division_role', ['ketua_media', 'kepala_divisi', 'anggota'])
            ->where('status', 'Aktif')
            ->orderBy('name')
            ->get();

        $result = [];

        foreach ($users as $user) {
            $completedTasks = ProjectTask::where('status', 'Completed')
                ->whereBetween('completed_at', [$start, $end])
                ->whereHas('contributors', function ($q) use ($user) {
                    $q->where('users.id', $user->id);
                })
                ->whereNotNull('task_date')
                ->get();

            $total = $completedTasks->count();
            $onTime = 0; $late = 0; $lateDaysSum = 0;

            foreach ($completedTasks as $task) {
                if (!$task->task_date || !$task->completed_at) continue;

                // ⚡ FIX: pakai helper isOnTime() -> perbandingan per TANGGAL
                if ($this->isOnTime($task)) {
                    $onTime++;
                } else {
                    $late++;
                    $lateDaysSum += $task->task_date->diffInDays($task->completed_at);
                }
            }

            $onTimeRate = $total > 0 ? round(($onTime / $total) * 100) : 0;
            $avgLateDays = $late > 0 ? round($lateDaysSum / $late, 1) : 0;

            $result[] = [
                'id'              => $user->id,
                'name'            => $user->name,
                'avatar'          => $user->avatar,
                'division'        => $user->division ?? '-',
                'division_role'   => $user->division_role ?? 'anggota',
                'total_completed' => $total,
                'on_time'         => $onTime,
                'late'            => $late,
                'on_time_rate'    => $onTimeRate,
                'avg_late_days'   => $avgLateDays,
            ];
        }

        usort($result, fn($a, $b) => $a['on_time_rate'] <=> $b['on_time_rate']);

        return $result;
    }

    // ============================================================
    // ALERT OTOMATIS
    // ============================================================

    private function buildAlerts(): array
    {
        $alerts = [];

        $inactiveUsers = User::whereIn('division_role', ['ketua_media', 'kepala_divisi', 'anggota'])
            ->where('status', 'Aktif')
            ->where(function ($q) {
                $q->where('last_login_date', '<', now()->subDays(7)->toDateString())
                  ->orWhereNull('last_login_date');
            })
            ->get();

        foreach ($inactiveUsers as $u) {
            $daysSince = $u->last_login_date ? $u->last_login_date->diffInDays(now()) : null;

            $alerts[] = [
                'type'     => 'inactive',
                'priority' => 'high',
                'icon'     => 'fa-user-clock',
                'color'    => 'warning',
                'title'    => $u->name . ' tidak login ' . ($daysSince ? $daysSince . ' hari' : '(belum pernah)'),
                'message'  => 'Divisi: ' . ($u->division ?? '-') . ' • Terakhir: '
                    . ($u->last_login_date ? $u->last_login_date->format('d M Y') : 'Belum pernah'),
                'url'      => route('profile.show', $u->id),
                'cta'      => 'Lihat Profil',
            ];
        }

        $start14 = now()->subDays(13)->startOfDay();
        $end = now()->endOfDay();

        $activeUsers = User::whereIn('division_role', ['kepala_divisi', 'anggota'])
            ->where('status', 'Aktif')
            ->get();

        foreach ($activeUsers as $u) {
            $hasPoints = ProjectTask::where(function ($q) use ($start14, $end) {
                    $q->whereBetween('task_date', [$start14->toDateString(), $end->toDateString()])
                      ->orWhere(function ($q2) use ($start14, $end) {
                          $q2->whereNull('task_date')
                             ->whereBetween('created_at', [$start14, $end]);
                      });
                })
                ->whereHas('contributors', function ($q) use ($u) {
                    $q->where('users.id', $u->id);
                })
                // ⚡ FIX (M4): hanya task yang benar-benar SELESAI yang dianggap "sudah dapat poin".
                // Sebelumnya cukup punya task berbobot (walau belum dikerjakan) -> alert tidak muncul
                // padahal orangnya belum menyelesaikan apa pun.
                ->where('status', 'Completed')
                ->where('weight', '>', 0)
                ->exists();

            if (!$hasPoints) {
                $alerts[] = [
                    'type'     => 'no_points',
                    'priority' => 'high',
                    'icon'     => 'fa-star',
                    'color'    => 'warning',
                    'title'    => $u->name . ' belum dapat poin',
                    'message'  => 'Divisi: ' . ($u->division ?? '-') . ' • 14 hari terakhir tanpa task dengan poin',
                    'url'      => route('profile.show', $u->id),
                    'cta'      => 'Cek',
                ];
            }
        }

        $thisWeekStart = now()->startOfWeek();
        $thisWeekEnd = now()->endOfWeek();
        $lastWeekStart = now()->subWeek()->startOfWeek();
        $lastWeekEnd = now()->subWeek()->endOfWeek();

        $divisions = ['Lapangan', 'Editing', 'Broadcast', 'AI Research'];

        foreach ($divisions as $divName) {
            // ⚡ FIX (M5): hanya anggota Aktif + atribusi pakai KONTRIBUTOR (fallback PIC),
            // konsisten dengan metrik lain (sebelumnya assigned_to saja dan tanpa filter status).
            $memberIds = User::where('division', 'like', "%{$divName}%")
                ->where('status', 'Aktif')
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->all();

            if (empty($memberIds)) continue;

            $attribusiDivisi = function ($q) use ($memberIds) {
                $q->where(function ($w) use ($memberIds) {
                    $w->whereHas('contributors', fn ($c) => $c->whereIn('users.id', $memberIds))
                      ->orWhere(function ($w2) use ($memberIds) {
                          $w2->whereDoesntHave('contributors')
                             ->whereIn('assigned_to', $memberIds);
                      });
                });
            };

            $thisWeek = ProjectTask::where('status', 'Completed')
                ->where($attribusiDivisi)
                ->whereBetween('completed_at', [$thisWeekStart, $thisWeekEnd])
                ->count();

            $lastWeek = ProjectTask::where('status', 'Completed')
                ->where($attribusiDivisi)
                ->whereBetween('completed_at', [$lastWeekStart, $lastWeekEnd])
                ->count();

            if ($lastWeek > 0) {
                $diff = (($thisWeek - $lastWeek) / $lastWeek) * 100;

                if ($diff <= -20) {
                    $alerts[] = [
                        'type'     => 'division_drop',
                        'priority' => 'high',
                        'icon'     => 'fa-chart-line',
                        'color'    => 'danger',
                        'title'    => 'Divisi ' . $divName . ' turun ' . round(abs($diff)) . '%',
                        'message'  => 'Minggu ini: ' . $thisWeek . ' task • Minggu lalu: ' . $lastWeek . ' task',
                        'url'      => route('division.show', 'Tim ' . $divName),
                        'cta'      => 'Lihat Divisi',
                    ];
                }
            }
        }

        $overdueTasks = ProjectTask::where('status', '!=', 'Completed')
            ->whereNotNull('task_date')
            ->where('task_date', '<', now()->subDays(7)->toDateString())
            ->with(['project', 'assignedUser'])
            ->take(10)
            ->get();

        foreach ($overdueTasks as $task) {
            // ⚡ FIX line 542: parse task_date jadi Carbon
            if (!$task->task_date) continue;

            $daysLate = Carbon::parse($task->task_date)->diffInDays(now());

            $alerts[] = [
                'type'     => 'task_overdue',
                'priority' => 'medium',
                'icon'     => 'fa-exclamation-triangle',
                'color'    => 'danger',
                'title'    => 'Task overdue ' . $daysLate . ' hari',
                'message'  => $task->title . ' • ' . ($task->assignedUser->name ?? 'Tanpa PIC')
                    . ' • ' . ($task->project->name ?? '-'),
                'url'      => route('projects.show', $task->project_id),
                'cta'      => 'Lihat Project',
            ];
        }

        $priorityOrder = ['critical' => 1, 'high' => 2, 'medium' => 3, 'low' => 4];
        usort($alerts, fn($a, $b) => ($priorityOrder[$a['priority']] ?? 99) <=> ($priorityOrder[$b['priority']] ?? 99));

        return $alerts;
    }

    // ============================================================
    // DIVISION SUMMARY
    // ============================================================

    private function buildDivisionSummary(Carbon $start, Carbon $end): array
    {
        $divisions = ['Lapangan', 'Editing', 'Broadcast', 'AI Research'];
        $result = [];

        foreach ($divisions as $divName) {
            $members = User::where('division', 'like', "%{$divName}%")
                ->where('status', 'Aktif')
                ->get();

            if ($members->isEmpty()) {
                $result[] = [
                    'name' => $divName, 'label' => 'Tim ' . $divName,
                    'member_count' => 0, 'total_points' => 0, 'avg_points' => 0,
                    'total_completed' => 0, 'on_time_rate' => 0,
                ];
                continue;
            }

            $totalPoints = 0; $totalCompleted = 0; $totalOnTime = 0;

            foreach ($members as $u) {
                $tasks = ProjectTask::where(function ($q) use ($start, $end) {
                        $q->whereBetween('task_date', [$start->toDateString(), $end->toDateString()])
                          ->orWhere(function ($q2) use ($start, $end) {
                              $q2->whereNull('task_date')
                                 ->whereBetween('created_at', [$start, $end]);
                          });
                    })
                    ->whereHas('contributors', function ($q) use ($u) {
                        $q->where('users.id', $u->id);
                    })
                    ->with(['contributors:id', 'assignedUser:id'])
                    ->get();

                foreach ($tasks as $task) {
                    $contributorIds = $task->contributors->pluck('id')->toArray();
                    if (empty($contributorIds) && $task->assigned_to) {
                        $contributorIds = [(int) $task->assigned_to];
                    }
                    if (empty($contributorIds)) continue;

                    if (in_array($u->id, $contributorIds)) {
                        // ⚡ FIX (M3): poin HANYA dari task yang benar-benar SELESAI.
                        // Sebelumnya semua task berbobot ikut dihitung -> total poin divisi palsu.
                        if ($task->status === 'Completed') {
                            $totalPoints += ((float) $task->weight) / count($contributorIds);
                        }

                        if ($task->status === 'Completed' && $task->task_date && $task->completed_at) {
                            $totalCompleted++;

                            // ⚡ FIX: pakai helper isOnTime() -> perbandingan per TANGGAL
                            if ($this->isOnTime($task)) {
                                $totalOnTime++;
                            }
                        }
                    }
                }
            }

            $memberCount = $members->count();
            $avgPoints = $memberCount > 0 ? round($totalPoints / $memberCount, 1) : 0;
            $onTimeRate = $totalCompleted > 0 ? round(($totalOnTime / $totalCompleted) * 100) : 0;

            $result[] = [
                'name'            => $divName,
                'label'           => 'Tim ' . $divName,
                'member_count'    => $memberCount,
                'total_points'    => round($totalPoints),
                'avg_points'      => $avgPoints,
                'total_completed' => $totalCompleted,
                'on_time_rate'    => $onTimeRate,
            ];
        }

        usort($result, fn($a, $b) => $b['total_points'] <=> $a['total_points']);

        return $result;
    }
}