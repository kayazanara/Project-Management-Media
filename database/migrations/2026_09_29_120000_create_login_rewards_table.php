<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ⚡ FITUR: Daily Login Reward — 2 poin untuk login PERTAMA di tiap hari.
 *
 * - tabel `login_rewards` = riwayat bonus sekaligus pengaman di level database
 *   (UNIQUE user_id + reward_date -> mustahil dapat 2x di hari yang sama)
 * - kolom `users.login_streak` = streak terakhir (biar tampil cepat tanpa hitung ulang)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('login_rewards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('reward_date');
            $table->unsignedInteger('points')->default(2);
            $table->unsignedInteger('streak')->default(1);
            $table->timestamps();

            // ⚡ pengaman utama "1x sehari" — bukan cuma di tampilan
            $table->unique(['user_id', 'reward_date']);
        });

        if (! Schema::hasColumn('users', 'login_streak')) {
            Schema::table('users', function (Blueprint $table) {
                $table->unsignedInteger('login_streak')->default(0);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('login_rewards');

        if (Schema::hasColumn('users', 'login_streak')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('login_streak');
            });
        }
    }
};
