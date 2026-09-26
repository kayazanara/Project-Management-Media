<?php

namespace App\Services;

use App\Models\Project;
use App\Models\ProjectRevision;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class ProjectWorkflowService
{
    // ============================================================
    // OVERDUE SYNC
    // ============================================================

    /**
     * Sinkronkan flag is_overdue untuk semua project.
     * Definisi overdue (PRD Part 2.4): deadline < today DAN status != Completed.
     *
     * Return jumlah project yang baru ditandai overdue.
     */
    public function syncOverdueFlags(): int
    {
        $today = Carbon::today();

        // 1. Tandai overdue untuk yang lewat deadline & belum completed
        $marked = Project::where('status', '!=', Project::STATUS_COMPLETED)
            ->where('is_overdue', false)
            ->whereNotNull('deadline')
            ->whereDate('deadline', '<', $today)
            ->update(['is_overdue' => true]);

        // 2. Reset flag untuk yang ternyata sudah completed atau deadline diperpanjang
        $reset = Project::where('is_overdue', true)
            ->where(function ($q) use ($today) {
                $q->where('status', Project::STATUS_COMPLETED)
                  ->orWhere(function ($q2) use ($today) {
                      $q2->whereNotNull('deadline')
                         ->whereDate('deadline', '>=', $today);
                  });
            })
            ->update(['is_overdue' => false]);

        if ($marked > 0 || $reset > 0) {
            Log::info("[Workflow] Overdue sync — ditandai: {$marked}, direset: {$reset}");
        }

        return $marked;
    }

    // ============================================================
    // RELEASE GUARD
    // ============================================================

    /**
     * Cek apakah project siap dirilis (Scoring → On Progress).
     * Syarat: SEMUA task sudah di-scoring & di-approve.
     */
    public function isReadyToRelease(Project $project): bool
    {
        $total = $project->tasks()->count();

        if ($total === 0) {
            return false;
        }

        $scored = $project->tasks()->whereNotNull('scored_at')->count();
        $approved = $project->tasks()->where('is_approved', true)->count();

        return $scored === $total && $approved === $total;
    }

    /**
     * Detail kesiapan rilis (untuk UI/validasi).
     * Return: ['total' => int, 'scored' => int, 'approved' => int, 'ready' => bool]
     */
    public function releaseReadiness(Project $project): array
    {
        $total = $project->tasks()->count();
        $scored = $project->tasks()->whereNotNull('scored_at')->count();
        $approved = $project->tasks()->where('is_approved', true)->count();

        return [
            'total'    => $total,
            'scored'   => $scored,
            'approved' => $approved,
            'ready'    => $total > 0 && $scored === $total && $approved === $total,
        ];
    }

    // ============================================================
    // PROGRESS & COMPLETION
    // ============================================================

    /**
     * Hitung progress project.
     * Return: ['total' => int, 'completed' => int, 'percent' => int]
     */
    public function progress(Project $project): array
    {
        $total = $project->tasks()->count();
        $completed = $project->tasks()->where('status', 'Completed')->count();

        return [
            'total'     => $total,
            'completed' => $completed,
            'percent'   => $total > 0 ? (int) round(($completed / $total) * 100) : 0,
        ];
    }

    /**
     * Cek apakah semua task sudah completed.
     * Dipakai untuk guard di requestReview (kalau mau strict).
     */
    public function allTasksCompleted(Project $project): bool
    {
        $total = $project->tasks()->count();
        if ($total === 0) {
            return false;
        }

        $completed = $project->tasks()->where('status', 'Completed')->count();
        return $total === $completed;
    }

    // ============================================================
    // REVISION
    // ============================================================

    /**
     * Tandai semua revisi yang masih open sebagai resolved.
     * Dipanggil setelah project di-approve (dua pihak ACC).
     */
    public function resolveOpenRevisions(Project $project): int
    {
        return ProjectRevision::where('project_id', $project->id)
            ->whereNull('resolved_at')
            ->update(['resolved_at' => now()]);
    }

    /**
     * Cek apakah project punya revisi yang belum diselesaikan.
     */
    public function hasOpenRevisions(Project $project): bool
    {
        return $project->openRevisions()->exists();
    }

    // ============================================================
    // PERMISSIONS
    // ============================================================

    /**
     * Cek apakah user diizinkan mengubah status project ke $newStatus.
     * Sesuai PRD Part 2.4 (Aturan Overdue) + Part 2.5 (State Machine).
     */
    public function canUserChangeStatus(Project $project, User $user, string $newStatus): bool
    {
        if (!$project->canTransitionTo($newStatus)) {
            return false;
        }

        // Manager (Pembina + Ketua Media) bebas ubah ke status apapun
        if ($user->isManager()) {
            return true;
        }

        // Kepala Divisi: hanya transisi normal On Progress → Review
        if ($user->isKepalaDivisi()) {
            return $project->status === Project::STATUS_ON_PROGRESS
                && $newStatus === Project::STATUS_REVIEW;
        }

        // Anggota: hanya lihat, tidak bisa ubah status
        return false;
    }

    // ============================================================
    // AVAILABLE ACTIONS
    // ============================================================

    /**
     * Daftar aksi yang bisa dilakukan user pada project ini,
     * sesuai status + role. Untuk render tombol di view.
     *
     * Return array of assoc:
     * [
     *   'key'     => 'release',
     *   'label'   => 'Rilis Project',
     *   'method'  => 'PATCH',
     *   'route'   => 'http://.../projects/1/release',
     *   'color'   => 'success',
     *   'icon'    => 'fa-rocket',
     *   'confirm' => 'Rilis project?',
     * ]
     */
    public function getAvailableActions(Project $project, User $user): array
    {
        $actions = [];
        $status = $project->status;

        // ── 1. Planning → Task Review (Ketua Media) ──
        if ($status === Project::STATUS_PLANNING && $user->isKetuaMedia()) {
            $taskCount = $project->tasks()->count();
            if ($taskCount > 0) {
                $actions[] = [
                    'key'     => 'submit_task_review',
                    'label'   => "Ajukan ke Pembina ({$taskCount} Task)",
                    'method'  => 'PATCH',
                    'route'   => route('projects.submitTaskReview', $project->id),
                    'color'   => 'primary',
                    'icon'    => 'fa-paper-plane',
                    'confirm' => 'Ajukan task ke Pembina untuk scoring?',
                ];
            }
        }

        // ── 2. Task Review → Scoring (Pembina, via modal) ──
        if ($status === Project::STATUS_TASK_REVIEW && $user->isPembina()) {
            $actions[] = [
                'key'     => 'submit_scoring',
                'label'   => 'Mulai Scoring',
                'method'  => 'PATCH',
                'route'   => route('projects.submitScoring', $project->id),
                'color'   => 'primary',
                'icon'    => 'fa-star',
                'confirm' => null, // via modal scoring
            ];
        }

        // ── 3. Scoring → On Progress (Pembina) ──
        if ($status === Project::STATUS_SCORING && $user->isPembina()) {
            if ($this->isReadyToRelease($project)) {
                $actions[] = [
                    'key'     => 'release',
                    'label'   => 'Rilis Project',
                    'method'  => 'PATCH',
                    'route'   => route('projects.release', $project->id),
                    'color'   => 'success',
                    'icon'    => 'fa-rocket',
                    'confirm' => 'Rilis project? Semua anggota akan menerima notifikasi.',
                ];
            }
        }

        // ── 4. On Progress → Review (Kepala Divisi) ──
        if ($status === Project::STATUS_ON_PROGRESS && $user->isKepalaDivisi()) {
            $actions[] = [
                'key'     => 'request_review',
                'label'   => 'Ajukan Review',
                'method'  => 'PATCH',
                'route'   => route('projects.requestReview', $project->id),
                'color'   => 'info',
                'icon'    => 'fa-search',
                'confirm' => 'Ajukan review? Pastikan semua task sudah selesai.',
            ];
        }

        // ── 5. Review → Approved / Revision (Manager) ──
        if ($status === Project::STATUS_REVIEW && $user->isManager()) {
            $canApprove = ($user->isPembina() && !$project->approved_by_pembina_at)
                       || ($user->isKetuaMedia() && !$project->approved_by_ketua_at);

            if ($canApprove) {
                $actions[] = [
                    'key'     => 'approve',
                    'label'   => 'Approve',
                    'method'  => 'PATCH',
                    'route'   => route('projects.approve', $project->id),
                    'color'   => 'success',
                    'icon'    => 'fa-check',
                    'confirm' => 'Setujui project ini?',
                ];
            }

            $actions[] = [
                'key'     => 'request_revision',
                'label'   => 'Minta Revisi',
                'method'  => 'PATCH',
                'route'   => route('projects.requestRevision', $project->id),
                'color'   => 'danger',
                'icon'    => 'fa-redo',
                'confirm' => null, // via modal revision
            ];
        }

        // ── 6. Revision → Review (Kepala Divisi) ──
        if ($status === Project::STATUS_REVISION && $user->isKepalaDivisi()) {
            $actions[] = [
                'key'     => 'request_review_again',
                'label'   => 'Ajukan Review Ulang',
                'method'  => 'PATCH',
                'route'   => route('projects.requestReview', $project->id),
                'color'   => 'info',
                'icon'    => 'fa-search',
                'confirm' => 'Ajukan review ulang?',
            ];
        }

        // ── 7. Upload → Completed (Manager / Kepala Divisi) ──
        if ($status === Project::STATUS_UPLOAD && ($user->isManager() || $user->isKepalaDivisi())) {
            $actions[] = [
                'key'     => 'mark_complete',
                'label'   => 'Mark as Complete',
                'method'  => 'PATCH',
                'route'   => route('projects.markComplete', $project->id),
                'color'   => 'success',
                'icon'    => 'fa-check-double',
                'confirm' => 'Tandai project sebagai COMPLETED?',
            ];
        }

        return $actions;
    }
}