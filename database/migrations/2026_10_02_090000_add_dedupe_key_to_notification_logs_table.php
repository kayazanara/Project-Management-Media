<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 🔁 PERBAIKAN PESAN WA DOBEL (2 Okt 2026)
 *
 * 1. `dedupe_key` — kunci anti-dobel yang lebih tepat daripada sekadar jenis pesan.
 *    Contoh: satu action item rapat -> kunci "rapat:12:tugas:abc123". Jadi tugas
 *    yang SAMA tidak dikirim ulang walau judul/lokasi rapat berubah, TAPI tugas
 *    yang berbeda tetap terkirim.
 *
 * 2. Status `pending` — baris dicatat SEBELUM pesan benar-benar dikirim
 *    (cadangan), sehingga dua permintaan cepat (klik dobel) tidak sama-sama lolos.
 *
 * Murni aditif: data lama tetap valid, `dedupe_key` NULL untuk baris lama.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('notification_logs', 'dedupe_key')) {
            Schema::table('notification_logs', function (Blueprint $table) {
                $table->string('dedupe_key')->nullable()->after('type');
                $table->index('dedupe_key');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('notification_logs', 'dedupe_key')) {
            Schema::table('notification_logs', function (Blueprint $table) {
                $table->dropIndex(['dedupe_key']);
                $table->dropColumn('dedupe_key');
            });
        }
    }
};
