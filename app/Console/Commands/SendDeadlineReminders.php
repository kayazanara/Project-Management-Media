<?php

namespace App\Console\Commands;

use App\Models\Project;
use App\Models\User;
use App\Services\ProjectWorkflowService;
use App\Services\WhatsappNotifier;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SendDeadlineReminders extends Command
{
    protected $signature   = 'reminders:send';
    protected $description = 'Kirim reminder H-3, H-1, dan Overdue ke anggota project';

    public function handle(): int
    {
        $notifier = app(WhatsappNotifier::class);
        $workflow = app(ProjectWorkflowService::class);

        // Sync overdue flag dulu
        $workflow->syncOverdueFlags();

        $today = Carbon::today();

        $sentCount = 0;
        $sentCount += $this->sendH3($notifier, $today);
        $sentCount += $this->sendH1($notifier, $today);
        $sentCount += $this->sendOverdue($notifier, $today);

        $this->info("✅ Reminder selesai. Total notif terkirim: {$sentCount}");
        return self::SUCCESS;
    }

    // ============================================================
    // H-3
    // ============================================================

    private function sendH3(WhatsappNotifier $notifier, Carbon $today): int
    {
        $targetDate = $today->copy()->addDays(3);

        $projects = Project::where('status', '!=', Project::STATUS_COMPLETED)
            ->where('is_overdue', false)
            ->whereDate('deadline', $targetDate->toDateString())
            ->with(['users', 'tasks'])
            ->get();

        if ($projects->isEmpty()) return 0;

        $this->line("H-3: {$projects->count()} project(s) menuju deadline {$targetDate->format('d M Y')}");

        $count = 0;
        foreach ($projects as $project) {
            $users = $this->getRecipients($project);
            $progress = $this->getProgress($project);
            $deadline = $project->deadline->translatedFormat('d F Y');
            $listAnggota = $project->users->pluck('name')->implode(', ') ?: '-';

            foreach ($users as $u) {
                if (empty($u->phone)) continue;
                try {
                    $msg = $notifier->reminderH3Template(
                        $u->name,
                        $project->name,
                        $deadline,
                        $listAnggota,
                        $progress,
                        $project->id
                    );
                    $notifier->sendAndLog($u, $project->id, 'reminder_h3', $msg);
                    $count++;
                } catch (\Throwable $e) {
                    Log::warning("[Reminder H-3] Gagal ke user {$u->id}: {$e->getMessage()}");
                }
            }
        }
        return $count;
    }

    // ============================================================
    // H-1
    // ============================================================

    private function sendH1(WhatsappNotifier $notifier, Carbon $today): int
    {
        $targetDate = $today->copy()->addDay();

        $projects = Project::where('status', '!=', Project::STATUS_COMPLETED)
            ->where('is_overdue', false)
            ->whereDate('deadline', $targetDate->toDateString())
            ->with(['users', 'tasks'])
            ->get();

        if ($projects->isEmpty()) return 0;

        $this->line("H-1: {$projects->count()} project(s) menuju deadline {$targetDate->format('d M Y')}");

        $count = 0;
        foreach ($projects as $project) {
            $users = $this->getRecipients($project);
            $progress = $this->getProgress($project);
            $deadline = $project->deadline->translatedFormat('d F Y');
            $listAnggota = $project->users->pluck('name')->implode(', ') ?: '-';

            foreach ($users as $u) {
                if (empty($u->phone)) continue;
                try {
                    $msg = $notifier->reminderH1Template(
                        $u->name,
                        $project->name,
                        $deadline,
                        $listAnggota,
                        $progress,
                        $project->id
                    );
                    $notifier->sendAndLog($u, $project->id, 'reminder_h1', $msg);
                    $count++;
                } catch (\Throwable $e) {
                    Log::warning("[Reminder H-1] Gagal ke user {$u->id}: {$e->getMessage()}");
                }
            }
        }
        return $count;
    }

    // ============================================================
    // Overdue
    // ============================================================

    private function sendOverdue(WhatsappNotifier $notifier, Carbon $today): int
    {
        $projects = Project::where('status', '!=', Project::STATUS_COMPLETED)
            ->where('is_overdue', true)
            ->with(['users', 'tasks'])
            ->get();

        if ($projects->isEmpty()) return 0;

        $this->line("Overdue: {$projects->count()} project(s)");

        $count = 0;
        foreach ($projects as $project) {
            $users = $this->getRecipients($project);
            $progress = $this->getProgress($project);
            $deadline = $project->deadline
                ? $project->deadline->translatedFormat('d F Y')
                : '-';
            $listAnggota = $project->users->pluck('name')->implode(', ') ?: '-';

            foreach ($users as $u) {
                if (empty($u->phone)) continue;
                try {
                    $msg = $notifier->overdueTemplate(
                        $u->name,
                        $project->name,
                        $deadline,
                        $progress,
                        $listAnggota,
                        $project->id
                    );
                    $notifier->sendAndLog($u, $project->id, 'overdue', $msg);
                    $count++;
                } catch (\Throwable $e) {
                    Log::warning("[Overdue] Gagal ke user {$u->id}: {$e->getMessage()}");
                }
            }
        }
        return $count;
    }

    // ============================================================
    // HELPERS
    // ============================================================

    /**
     * Penerima = semua user yang terlibat project + semua Kepala Divisi aktif.
     */
    private function getRecipients(Project $project)
    {
        $memberIds = $project->users->pluck('id')->toArray();

        return User::where(function ($q) use ($memberIds) {
                $q->whereIn('id', $memberIds)
                  ->orWhere('division_role', 'kepala_divisi');
            })
            ->where('status', 'Aktif')
            ->get();
    }

    /**
     * Progress = completed tasks / total tasks × 100
     */
    private function getProgress(Project $project): int
    {
        $total = $project->tasks->count();
        if ($total === 0) return 0;

        $done = $project->tasks->where('status', 'Completed')->count();
        return (int) round(($done / $total) * 100);
    }
}