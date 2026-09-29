<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ⚡ FITUR BARU: menu "Kinerja Ketua Media" (khusus Pembina).
 *
 * Tabel `ketua_media_evaluations` = catatan evaluasi manual yang ditulis Pembina
 * untuk Ketua Media per periode (mis. evaluasi rapat bulanan), lengkap dengan
 * SNAPSHOT skor saat catatan dibuat supaya angka di catatan tidak berubah
 * walaupun data di belakangnya bergerak.
 *
 * Murni tambahan: tidak menyentuh tabel/kolom yang sudah ada.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ketua_media_evaluations', function (Blueprint $table) {
            $table->id();

            // Ketua Media yang dinilai
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();

            // Pembina yang menulis catatan
            $table->foreignId('evaluator_id')->nullable()->constrained('users')->nullOnDelete();

            // Periode yang dievaluasi
            $table->date('period_start')->nullable();
            $table->date('period_end')->nullable();

            // Snapshot skor kinerja saat catatan dibuat (biar tidak "bergerak" ikut data)
            $table->unsignedTinyInteger('score')->nullable();
            $table->string('rating', 40)->nullable();

            $table->text('note');

            $table->timestamps();

            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ketua_media_evaluations');
    }
};
