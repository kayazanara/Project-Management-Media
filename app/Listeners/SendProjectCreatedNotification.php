<?php

namespace App\Listeners;

use App\Events\ProjectCreated;
use App\Models\User;
use App\Services\WhatsappNotifier;
use Illuminate\Support\Facades\Log;

class SendProjectCreatedNotification
{
    protected WhatsappNotifier $notifier;

    public function __construct(WhatsappNotifier $notifier)
    {
        $this->notifier = $notifier;
    }

    public function handle(ProjectCreated $event): void
    {
        $project = $event->project;
        $project->loadMissing('users');

        // Penerima: semua Ketua Media aktif
        $recipients = User::where('division_role', 'ketua_media')
            ->where('status', 'Aktif')
            ->get();

        if ($recipients->isEmpty()) {
            Log::info("[ProjectCreated] Tidak ada Ketua Media aktif untuk project #{$project->id}");
            return;
        }

        $startDate = $project->start_date
            ? $project->start_date->translatedFormat('d F Y')
            : '-';

        $deadline = $project->deadline
            ? $project->deadline->translatedFormat('d F Y')
            : '-';

        $listAnggota = $project->users->pluck('name')->implode(', ');
        if (empty($listAnggota)) {
            $listAnggota = '-';
        }

        $brief = $project->brief ?: 'Tidak ada brief khusus.';

        foreach ($recipients as $user) {
            if (empty($user->phone)) {
                continue;
            }

            try {
                $message = $this->notifier->projectCreatedTemplate(
                    $user->name,
                    $project->name,
                    $startDate,
                    $deadline,
                    $listAnggota,
                    $brief,
                    $project->id
                );

                $this->notifier->sendAndLog(
                    $user,
                    $project->id,
                    'project_created',
                    $message
                );
            } catch (\Throwable $e) {
                Log::warning("[ProjectCreated] Gagal kirim ke user {$user->id}: {$e->getMessage()}");
            }
        }
    }
}