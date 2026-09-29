<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ⚡ FITUR BARU: pengukuran KECEPATAN ALUR Ketua Media.
 *
 * Sebelumnya tidak ada penanda waktu untuk dua aksi kunci Ketua Media:
 *   1. kapan dia mengajukan task ke Pembina (Planning -> Task Review)
 *      → `task_submitted_at` + `task_submitted_by`
 *   2. kapan sebuah project MASUK tahap Review (mulai menunggu ACC dia)
 *      → `review_requested_at`
 *
 * Tanpa dua kolom ini, "berapa lama project nyangkut menunggu Ketua Media"
 * hanya bisa ditebak dari updated_at (tidak jujur, karena updated_at berubah
 * setiap ada perubahan lain).
 *
 * Kolom baru = NULL untuk data lama (aditif). Data historis yang belum punya
 * penanda akan ditampilkan sebagai "—" di halaman, dan angka rata-rata mulai
 * terkumpul sejak fitur ini aktif.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            if (! Schema::hasColumn('projects', 'task_submitted_at')) {
                $table->dateTime('task_submitted_at')->nullable()->after('scored_at');
            }
            if (! Schema::hasColumn('projects', 'task_submitted_by')) {
                $table->foreignId('task_submitted_by')->nullable()->after('task_submitted_at')
                    ->constrained('users')->nullOnDelete();
            }
            if (! Schema::hasColumn('projects', 'review_requested_at')) {
                $table->dateTime('review_requested_at')->nullable()->after('task_submitted_by');
            }
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            if (Schema::hasColumn('projects', 'task_submitted_by')) {
                $table->dropConstrainedForeignId('task_submitted_by');
            }
            foreach (['review_requested_at', 'task_submitted_at'] as $kolom) {
                if (Schema::hasColumn('projects', $kolom)) {
                    $table->dropColumn($kolom);
                }
            }
        });
    }
};
