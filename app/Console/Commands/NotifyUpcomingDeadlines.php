<?php

namespace App\Console\Commands;

use App\Jobs\NotifyDeadlineReminder;
use App\Models\Project;
use Carbon\Carbon;
use Illuminate\Console\Command;

class NotifyUpcomingDeadlines extends Command
{
    protected $signature = 'notify:deadlines
                            {--dry-run : Tampilkan saja, tidak kirim notifikasi}
                            {--type= : Force hanya 1 tipe (h3|h1|overdue)}';

    protected $description = 'Scan project & kirim reminder H-3, H-1, dan Overdue ke WhatsApp';

    public function handle(): int
    {
        $today = Carbon::today();
        $dryRun = $this->option('dry-run');
        $forceType = $this->option('type');

        $this->info("🔍 Scanning project... (Hari ini: {$today->format('d M Y')})");
        if ($dryRun) {
            $this->warn("⚠️  DRY RUN — tidak akan kirim notifikasi");
        }
        $this->newLine();

        // ============================================
        // 1. Reminder H-3
        // ============================================
        if (!$forceType || $forceType === 'h3') {
            $h3Target = $today->copy()->addDays(3)->toDateString();

            $projects = Project::where('deadline', $h3Target)
                ->where('status', '!=', 'Completed')
                ->where('status', '!=', 'Overdue')
                ->with('users')
                ->get();

            $this->info("📅 H-3 (deadline: {$h3Target}) — {$projects->count()} project");

            foreach ($projects as $project) {
                $this->line("   → #{$project->id} {$project->name} ({$project->users->count()} anggota)");

                if (!$dryRun) {
                    NotifyDeadlineReminder::dispatch($project->id, 'reminder_h3');
                }
            }
        }

        // ============================================
        // 2. Reminder H-1
        // ============================================
        if (!$forceType || $forceType === 'h1') {
            $h1Target = $today->copy()->addDay()->toDateString();

            $projects = Project::where('deadline', $h1Target)
                ->where('status', '!=', 'Completed')
                ->where('status', '!=', 'Overdue')
                ->with('users')
                ->get();

            $this->info("📅 H-1 (deadline: {$h1Target}) — {$projects->count()} project");

            foreach ($projects as $project) {
                $this->line("   → #{$project->id} {$project->name} ({$project->users->count()} anggota)");

                if (!$dryRun) {
                    NotifyDeadlineReminder::dispatch($project->id, 'reminder_h1');
                }
            }
        }

        // ============================================
        // 3. Overdue
        // ============================================
        if (!$forceType || $forceType === 'overdue') {
            $projects = Project::where('deadline', '<', $today->toDateString())
                ->where('status', '!=', 'Completed')
                ->with('users')
                ->get();

            $this->info("📅 Overdue (deadline < {$today->toDateString()}) — {$projects->count()} project");

            foreach ($projects as $project) {
                $this->line("   → #{$project->id} {$project->name} ({$project->users->count()} anggota)");

                if (!$dryRun) {
                    NotifyDeadlineReminder::dispatch($project->id, 'overdue');
                }
            }
        }

        $this->newLine();
        $this->info($dryRun ? '✅ Dry run selesai.' : '✅ Notifikasi di-dispatch ke queue.');

        return self::SUCCESS;
    }
}
