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
            'performance' => $this->buildDivisionPerformance($days),
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

    public function getPerformance(Request $request)
    {
        $user = Auth::user();
        if (!$user->isManager()) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        // ⚡ Periode mengikuti filter halaman, sama seperti tampilan utama
        $days = (int) $request->input('days', 30);
        if (!in_array($days, [7, 30, 90])) {
            $days = 30;
        }

        return response()->json([
            'performance' => $this->buildDivisionPerformance($days),
            'periode_days' => $days,
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
        // ⚡ FIX (B1): dan dihitung dari tanggal SELESAI (completed_at) pada periode terpilih,
        // konsisten dengan halaman Monitoring & Dashboard.
        $tasks = ProjectTask::where('status', 'Completed')
            ->whereBetween('completed_at', [$start, $end])
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

        // FITUR BARU: tambahkan bonus login harian per anggota (sumber tunggal: DailyLoginReward)
        foreach (app(\App\Services\DailyLoginReward::class)->pointsPerUser($start, $end) as $uid => $poinLogin) {
            if (! isset($result[$uid])) {
                $result[$uid] = ['points' => 0, 'tasks' => 0];
            }

            $result[$uid]['points'] += $poinLogin;
        }

        return $result;
    }

    // ============================================================
    // COLOR MAPPING
    // ============================================================

    /**
     * Penentu "task ini punya divisi ini atau bukan".
     * Aturan sama dengan sistem poin: pakai KONTRIBUTOR; kalau task belum punya
     * kontributor, pakai PIC (assigned_to).
     */
    private function pelakuTask(ProjectTask $task, array $memberIds): bool
    {
        $pelaku = $task->contributors->pluck('id')->map(fn ($i) => (int) $i)->all();

        if ($pelaku === []) {
            $pelaku = $task->assigned_to ? [(int) $task->assigned_to] : [];
        }

        return $pelaku !== [] && array_intersect($pelaku, $memberIds) !== [];
    }

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

    /**
     * 📊 Performa Divisi — SATU basis periode untuk semua angka.
     *
     * ⚡ Diperbaiki 2 Okt 2026 (permintaan Zev: "terutama di bagian performa devisi").
     * Yang salah sebelumnya:
     *  1. Periode selalu minggu ini -> filter 7/30/90 hari tidak berpengaruh.
     *  2. Pembanding tren tidak apple-to-apple: pembilang memakai gabungan
     *     (task_date periode ini ATAU selesai periode ini), pembanding hanya
     *     "selesai minggu lalu" -> bisa salah tanda.
     *  3. Periode lalu 0 task -> ditulis 0%, padahal divisi baru naik dari nol.
     *  4. Divisi tanpa task -> semua 0% (kelihatan buruk), padahal artinya
     *     "tidak ada data" -> sekarang "—" (prinsip kejujuran data).
     *  5. Bobot (weight) yang belum pernah diisi Pembina ikut menekan skor ->
     *     sekarang indikator tanpa data dilewati dan bobotnya dibagi proporsional.
     */
    private function buildDivisionPerformance(int $days = 30): array
    {
        $divisions = $this->getDivisionColors();

        $mulai     = Carbon::now()->subDays($days - 1)->startOfDay();
        $akhir     = Carbon::now()->endOfDay();
        $mulaiLalu = $mulai->copy()->subDays($days)->startOfDay();
        $akhirLalu = $mulai->copy()->subDay()->endOfDay();

        // 1) anggota AKTIF per divisi — satu query untuk semua divisi
        $anggotaDivisi = [];
        foreach (User::where('status', 'Aktif')->get(['id', 'division']) as $u) {
            if (! $u->division) {
                continue;
            }
            foreach ($divisions as $nama => $meta) {
                if (stripos($u->division, $nama) !== false) {
                    $anggotaDivisi[$nama][] = (int) $u->id;
                }
            }
        }

        // 2) task yang relevan: dijadwalkan/selesai periode ini, atau selesai periode lalu
        $tugas = ProjectTask::where(function ($q) use ($mulai, $akhir, $mulaiLalu, $akhirLalu) {
                $q->whereBetween('task_date', [$mulai->toDateString(), $akhir->toDateString()])
                  ->orWhereBetween('completed_at', [$mulai, $akhir])
                  ->orWhereBetween('completed_at', [$mulaiLalu, $akhirLalu]);
            })
            ->with('contributors:id')
            ->get(['id', 'assigned_to', 'task_date', 'status', 'completed_at', 'weight']);

        // 3) project yang tuntas pada periode ini (untuk info tambahan)
        $projectTuntas = Project::where('status', Project::STATUS_COMPLETED)
            ->whereBetween('completed_at', [$mulai, $akhir])
            ->with('users:id')
            ->get(['id', 'completed_at']);

        $result = [];

        foreach ($divisions as $divName => $meta) {
            $ids = $anggotaDivisi[$divName] ?? [];

            $milikDivisi = $ids === []
                ? collect()
                : $tugas->filter(fn ($t) => $this->pelakuTask($t, $ids));

            // ✅ SATU basis periode: task yang DIJADWALKAN periode ini atau SELESAI periode ini
            $periode = $milikDivisi->filter(function ($t) use ($mulai, $akhir) {
                $dijadwalkan = $t->task_date && $t->task_date->between($mulai, $akhir);
                $selesaiDi   = $t->completed_at && $t->completed_at->between($mulai, $akhir);

                return $dijadwalkan || $selesaiDi;
            });

            // ✅ pembilang: hanya yang BENAR-BENAR selesai DI DALAM periode ini
            $selesai = $periode->filter(fn ($t) => $t->status === 'Completed'
                && $t->completed_at
                && $t->completed_at->between($mulai, $akhir));

            // ✅ pembanding tren: panjang periode SAMA, satu basis yang sama
            $selesaiLalu = $milikDivisi->filter(fn ($t) => $t->status === 'Completed'
                && $t->completed_at
                && $t->completed_at->between($mulaiLalu, $akhirLalu))->count();

            $totalTasks     = $periode->count();
            $completedTasks = $selesai->count();

            // tepat waktu dibandingkan per TANGGAL (datetime vs date pernah bikin 0% selamanya)
            $tepatWaktu = $selesai->filter(fn ($t) => $t->task_date
                && $t->completed_at->toDateString() <= $t->task_date->toDateString())->count();

            $bobotPeriode  = (int) $periode->sum('weight');
            $bobotSelesai  = (int) $selesai->sum('weight');

            // null = tidak ada data (ditampilkan "—"), bukan 0
            $completionRate = $totalTasks > 0
                ? (int) min(100, round(($completedTasks / $totalTasks) * 100))
                : null;
            $onTimeRate = $completedTasks > 0
                ? (int) min(100, round(($tepatWaktu / $completedTasks) * 100))
                : null;
            $weightedRate = $bobotPeriode > 0
                ? (int) min(100, round(($bobotSelesai / $bobotPeriode) * 100))
                : null;

            // ✅ skor: rata-rata dari indikator yang ADA datanya
            // (bobot dikembalikan proporsional -> 40/40, 40/40/20, dst.)
            $indikator = [];
            if ($completionRate !== null) { $indikator[] = [$completionRate, 40]; }
            if ($onTimeRate !== null)     { $indikator[] = [$onTimeRate, 40]; }
            if ($weightedRate !== null)   { $indikator[] = [$weightedRate, 20]; }

            $bobotIndikator = array_sum(array_column($indikator, 1));
            $performanceScore = ($indikator === [] || $bobotIndikator === 0)
                ? null
                : (int) round(array_sum(array_map(fn ($i) => $i[0] * $i[1], $indikator)) / $bobotIndikator);

            // ✅ tren: naik dari nol ditulis "baru", bukan 0%
            $trend     = null;
            $trendTeks = '—';

            if ($selesaiLalu > 0) {
                $trend     = (int) round((($completedTasks - $selesaiLalu) / $selesaiLalu) * 100);
                $trendTeks = ($trend > 0 ? '+' : '') . $trend . '%';
            } elseif ($completedTasks > 0) {
                $trendTeks = 'baru';
            }

            $selesaiId = $ids;
            $completedProjects = $projectTuntas->filter(function ($p) use ($selesaiId) {
                return $p->users->pluck('id')->map(fn ($i) => (int) $i)->intersect($selesaiId)->isNotEmpty();
            })->count();

            $result[] = [
                'name'                => $divName,
                'label'               => $meta['label'],
                'hex'                 => $meta['hex'],
                'completion_rate'     => $completionRate,
                'on_time_rate'        => $onTimeRate,
                'weighted_rate'       => $weightedRate,
                'performance_score'   => $performanceScore,
                'skor_teks'           => $performanceScore === null ? '—' : $performanceScore . '%',
                'total_tasks'         => $totalTasks,
                'completed_tasks'     => $completedTasks,
                'completed_prev'      => $selesaiLalu,
                'completed_projects'  => $completedProjects,
                'trend'               => $trend,
                'trend_teks'          => $trendTeks,
                'periode_teks'        => $mulai->isoFormat('D MMM') . ' – ' . $akhir->isoFormat('D MMM Y'),
            ];
        }

        return $result;
    }

    /**
     * 📊 Tren harian: JUMLAH task selesai PER HARI untuk tiap divisi.
     *
     * ⚡ Diperbaiki 2 Okt 2026 (permintaan Zev: "trend itu perhitungannya bisa
     * diperbaiki"). Versi lama rumusnya KUMULATIF:
     *     (task selesai sampai tanggal itu) / (total task selesai periode) x 100
     * sehingga grafiknya selalu naik 0 -> 100%, hari terakhir SELALU 100%, dan
     * tidak pernah turun walau hari itu tidak ada task selesai — menyesatkan.
     * Sekarang: jumlah task selesai HARI ITU saja, jadi naik-turunnya kelihatan.
     *
     * Bonus efisiensi: dulu 1 query COUNT per divisi per hari
     * (4 divisi x 90 hari = 360 query tiap halaman dibuka). Sekarang cukup
     * 1 query anggota + 1 query task (+1 query kontributor) untuk semua titik.
     */
    private function buildDailyCompletionTrend(int $days = 30): array
    {
        $divisions = $this->getDivisionColors();

        $end   = Carbon::now()->endOfDay();
        $start = Carbon::now()->subDays($days - 1)->startOfDay();

        // 1) daftar tanggal (sumbu X)
        $dates = [];
        $current = $start->copy();
        while ($current->lte($end)) {
            $dates[] = $current->toDateString();
            $current->addDay();
        }

        // 2) anggota AKTIF per divisi — satu query untuk semua divisi
        $anggotaDivisi = [];
        foreach (User::where('status', 'Aktif')->get(['id', 'division']) as $u) {
            if (! $u->division) {
                continue;
            }
            foreach ($divisions as $nama => $meta) {
                if (stripos($u->division, $nama) !== false) {
                    $anggotaDivisi[$nama][] = (int) $u->id;
                }
            }
        }

        // 3) semua task yang SELESAI pada periode ini — satu query
        $tugas = ProjectTask::where('status', 'Completed')
            ->whereBetween('completed_at', [$start, $end])
            ->with('contributors:id')
            ->get(['id', 'assigned_to', 'completed_at']);

        // 4) hitungkan per divisi per hari
        $perDivisi = [];
        foreach ($divisions as $nama => $meta) {
            $perDivisi[$nama] = array_fill_keys($dates, 0);
        }

        $totalSemua = 0;

        foreach ($tugas as $t) {
            if (! $t->completed_at) {
                continue;
            }

            $hari = $t->completed_at->toDateString();
            if (! array_key_exists($hari, $perDivisi[array_key_first($perDivisi)] ?? [])) {
                continue; // di luar rentang (pengaman)
            }

            $totalSemua++;

            // Atribusi kerja disamakan dengan sistem poin: pakai KONTRIBUTOR,
            // kalau task belum punya kontributor pakai PIC (assigned_to).
            $pelaku = $t->contributors->pluck('id')->map(fn ($i) => (int) $i)->all();
            if ($pelaku === []) {
                $pelaku = $t->assigned_to ? [(int) $t->assigned_to] : [];
            }
            if ($pelaku === []) {
                continue;
            }

            foreach ($divisions as $nama => $meta) {
                if (array_intersect($pelaku, $anggotaDivisi[$nama] ?? []) !== []) {
                    $perDivisi[$nama][$hari]++;
                }
            }
        }

        $datasets = [];
        foreach ($divisions as $nama => $meta) {
            $data = array_values($perDivisi[$nama]);

            $datasets[] = [
                'label' => $meta['label'],
                'hex'   => $meta['hex'],
                'data'  => $data,
                'total' => array_sum($data),
            ];
        }

        return [
            'labels'   => array_map(fn ($d) => Carbon::parse($d)->format('d M'), $dates),
            'datasets' => $datasets,
            'days'     => $days,
            'total'    => $totalSemua,
            'satuan'   => 'task selesai per hari',
        ];
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
            // ⚡ FIX (C4): ambang "< 3 poin" itu arbitrer; diselaraskan dengan pita Monitoring
            // (poin < 20 = "Ringan") supaya angka antar halaman konsisten.
            ->filter(fn($u) => $u['points'] < 20)
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