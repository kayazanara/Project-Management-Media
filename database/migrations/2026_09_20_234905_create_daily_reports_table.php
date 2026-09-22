<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('daily_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade'); // Kepala Divisi yang mengisi
            $table->string('division'); // Nama Divisi (misal: Tim Lapangan)
            $table->foreignId('project_id')->nullable()->constrained('projects')->onDelete('set null');
            $table->date('report_date');
            $table->text('summary')->nullable(); // Ringkasan otomatis / manual
            $table->text('completed_work')->nullable(); // Pekerjaan yang selesai
            $table->text('unfinished_work')->nullable(); // Pekerjaan belum selesai
            $table->text('problems')->nullable(); // Kendala
            $table->text('next_plan')->nullable(); // Rencana berikutnya
            $table->text('notes')->nullable(); // Catatan tambahan Kepala Divisi
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_reports');
    }
};