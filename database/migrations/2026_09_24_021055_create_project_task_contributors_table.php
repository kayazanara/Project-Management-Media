<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_task_contributors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_task_id')->constrained('project_tasks')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['project_task_id', 'user_id'], 'task_contributor_unique');
        });

        // ==========================================================
        // BACKFILL: Task lama yang sudah Completed & punya PIC
        // → PIC otomatis jadi kontributor
        // ==========================================================
        $existingTasks = DB::table('project_tasks')
            ->where('status', 'Completed')
            ->whereNotNull('assigned_to')
            ->get();

        $rows = [];
        foreach ($existingTasks as $task) {
            $rows[] = [
                'project_task_id' => $task->id,
                'user_id' => $task->assigned_to,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        if (!empty($rows)) {
            DB::table('project_task_contributors')->insert($rows);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('project_task_contributors');
    }
};