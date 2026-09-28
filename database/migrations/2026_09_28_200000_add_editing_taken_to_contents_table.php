<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ⚡ FASE D: penanda "bahan raw sudah diambil untuk diedit" + siapa yang mengambil.
 * Dua kolom baru (nullable) — aman, tidak mengubah/menghapus data yang ada.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contents', function (Blueprint $table) {
            $table->timestamp('taken_at')->nullable();
            $table->unsignedBigInteger('taken_by')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('contents', function (Blueprint $table) {
            $table->dropColumn(['taken_at', 'taken_by']);
        });
    }
};
