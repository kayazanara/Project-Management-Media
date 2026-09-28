<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ⚡ FASE A6: catat siapa yang mengunggah konten.
 * Dipakai supaya SETIAP ketua divisi bisa edit/menghapus activity yang dia upload sendiri
 * (sebelumnya tabel contents tidak menyimpan pengunggah sama sekali).
 * Kolom nullable — data lama tetap aman (created_by = NULL berarti hanya atasan yang bisa edit).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contents', function (Blueprint $table) {
            $table->unsignedBigInteger('created_by')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('contents', function (Blueprint $table) {
            $table->dropColumn('created_by');
        });
    }
};
