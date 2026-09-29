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
        // ⚡ AUDIT KEAMANAN (2026): seeder ini MENGHAPUS data. Sebelumnya bisa dijalankan
        // kapan saja & di mana saja — satu perintah di server = seluruh project, task,
        // konten, notulen, dan riwayat revisi HILANG tanpa peringatan.
        if (app()->environment('production')) {
            $this->command?->error('❌ DIBATALKAN: ResetDemoDataSeeder tidak boleh dijalankan di environment production.');

            return;
        }

        if (! $this->command || ! $this->command->confirm(
            '⚠️  Seeder ini akan MENGHAPUS SEMUA data project/task/konten/notulen. Yakin lanjut?',
            false
        )) {
            $this->command?->warn('Dibatalkan — tidak ada data yang dihapus.');

            return;
        }

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