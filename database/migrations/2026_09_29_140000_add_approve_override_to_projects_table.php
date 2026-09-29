<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ⚡ FITUR BARU: "Setujui & Lanjut Sekarang" — Pembina bisa melanjutkan project
 * dari tahap Review TANPA menunggu ACC Ketua Media.
 *
 * Kolom tambahan di `projects` (murni aditif, NULL untuk data lama):
 * - approved_override_by   : siapa Pembina yang memakai opsi ini
 * - approved_override_at   : kapan
 * - approved_override_note : ALASAN (wajib diisi, min 10 karakter)
 *
 * ⚡ PENTING & SENGAJA: `approved_by_ketua_at` TIDAK diisi oleh fitur ini.
 * Ketua Media memang belum menyetujui, jadi datanya harus tetap jujur
 * (tetap tertulis "Belum ACC") dan override-nya tercatat terpisah di sini.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            if (! Schema::hasColumn('projects', 'approved_override_by')) {
                $table->foreignId('approved_override_by')->nullable()->after('approved_by_ketua_at')
                    ->constrained('users')->nullOnDelete();
            }
            if (! Schema::hasColumn('projects', 'approved_override_at')) {
                $table->dateTime('approved_override_at')->nullable()->after('approved_override_by');
            }
            if (! Schema::hasColumn('projects', 'approved_override_note')) {
                $table->text('approved_override_note')->nullable()->after('approved_override_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            if (Schema::hasColumn('projects', 'approved_override_by')) {
                $table->dropConstrainedForeignId('approved_override_by');
            }
            foreach (['approved_override_at', 'approved_override_note'] as $kolom) {
                if (Schema::hasColumn('projects', $kolom)) {
                    $table->dropColumn($kolom);
                }
            }
        });
    }
};
