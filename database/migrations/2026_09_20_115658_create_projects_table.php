<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('projects', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // Nama Project
            $table->date('start_date'); // Tanggal masuk project
            $table->date('deadline'); // Deadline
            $table->string('status')->default('Planning'); // Status otomatis Planning
            $table->text('reference_link')->nullable(); // Link Referensi
            $table->text('brief')->nullable(); // Brief (menggantikan catatan)
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('projects');
    }
};