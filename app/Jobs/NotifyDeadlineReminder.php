<?php

namespace App\Jobs;

use App\Models\Project;
use App\Services\WhatsappNotifier;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class NotifyDeadlineReminder implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 30;

    /**
     * @param int    $projectId
     * @param string $type  'reminder_h3' | 'reminder_h1' | 'overdue'
     */
    public function __construct(
        public int $projectId,
        public string $type
    ) {
    }

    public function handle(WhatsappNotifier $notifier): void
    {
        $project = Project::with(['users', 'tasks'])->find($this->projectId);

        if (!$project) {
            Log::warning("Project #{$this->projectId} tidak ditemukan untuk reminder");
            return;
        }

        if ($project->users->isEmpty()) {
            Log::info("Project #{$project->id} tidak punya anggota, skip reminder");
            return;
        }

        // Hitung progress
        $totalTasks = $project->tasks->count();
        $completedTasks = $project->tasks->where('status', 'Completed')->count();
        $progress = $totalTasks > 0 ? round(($completedTasks / $totalTasks) * 100) : 0;

        // Format data
        $listAnggota = $project->users->pluck('name')->implode(', ');
        $deadline = Carbon::parse($project->deadline)->format('d M Y');

        Log::info("Kirim {$this->type} Project #{$project->id} ({$project->name}) ke {$project->users->count()} anggota");

        foreach ($project->users as $user) {
            if (empty($user->phone)) {
                Log::info("User {$user->id} ({$user->name}) tidak punya nomor WA, skip.");
                continue;
            }

            // Cek duplikat — kalau sudah pernah kirim, skip
            if (\App\Models\NotificationLog::alreadySent($this->type, $project->id, $user->id)) {
                continue;
            }

            // Pilih template sesuai tipe
            $message = match ($this->type) {
                'reminder_h3' => $notifier->reminderH3Template(
                    $user->name, $project->name, $deadline, $listAnggota, $progress, $project->id
                ),
                'reminder_h1' => $notifier->reminderH1Template(
                    $user->name, $project->name, $deadline, $listAnggota, $progress, $project->id
                ),
                'overdue' => $notifier->overdueTemplate(
                    $user->name, $project->name, $deadline, $progress, $listAnggota, $project->id
                ),
                default => null,
            };

            if (!$message) {
                Log::error("Tipe reminder tidak dikenal: {$this->type}");
                return;
            }

            $success = $notifier->sendAndLog($user, $project->id, $this->type, $message);

            if ($success) {
                Log::info("✅ {$this->type} terkirim ke {$user->name} ({$user->phone})");
            } else {
                Log::warning("❌ {$this->type} gagal ke {$user->name} ({$user->phone})");
            }
        }
    }
}