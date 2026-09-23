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

class NotifyProjectCreated implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 30;

    public function __construct(public int $projectId)
    {
    }

    public function handle(WhatsappNotifier $notifier): void
    {
        $project = Project::with('users')->find($this->projectId);

        if (!$project) {
            Log::warning("Project #{$this->projectId} tidak ditemukan untuk notifikasi");
            return;
        }

        if ($project->users->isEmpty()) {
            Log::info("Project #{$project->id} tidak punya anggota, skip notifikasi");
            return;
        }

        // Format data untuk template
        $listAnggota = $project->users->pluck('name')->implode(', ');
        $startDate = Carbon::parse($project->start_date)->format('d M Y');
        $deadline = Carbon::parse($project->deadline)->format('d M Y');
        $brief = $project->brief ?: '-';

        Log::info("Kirim notif Project Baru #{$project->id} ke {$project->users->count()} anggota");

        foreach ($project->users as $user) {
            if (empty($user->phone)) {
                Log::info("User {$user->id} ({$user->name}) tidak punya nomor WA, skip.");
                continue;
            }

            $message = $notifier->projectCreatedTemplate(
                $user->name,
                $project->name,
                $startDate,
                $deadline,
                $listAnggota,
                $brief,
                $project->id
            );

            $success = $notifier->sendAndLog($user, $project->id, 'project_created', $message);

            if ($success) {
                Log::info("✅ Notif terkirim ke {$user->name} ({$user->phone})");
            } else {
                Log::warning("❌ Notif gagal ke {$user->name} ({$user->phone})");
            }
        }
    }
}