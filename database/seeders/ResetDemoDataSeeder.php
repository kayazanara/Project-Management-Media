<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Project;
use App\Models\ProjectTask;
use App\Models\ProjectRevision;
use App\Models\Content;
use App\Models\Meeting;
use App\Models\WorkdeskAlert;
use App\Models\NotificationLog;

class ResetDemoDataSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->warn('⚠️  Menghapus SEMUA data demo...');

        NotificationLog::truncate();
        WorkdeskAlert::truncate();
        ProjectRevision::truncate();
        ProjectTask::query()->each(function ($task) {
            $task->contributors()->detach();
            $task->delete();
        });
        Content::truncate();
        Meeting::truncate();
        \DB::table('project_user')->truncate();
        Project::truncate();

        $this->command->info('✅ Semua data demo dihapus.');
        $this->command->info('   Akun test tetap ada (tidak dihapus).');
    }
}