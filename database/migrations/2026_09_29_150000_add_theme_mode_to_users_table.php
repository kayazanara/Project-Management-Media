<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ⚡ FITUR: Mode Gelap (dark mode).
 *
 * Pilihan tema disimpan per akun supaya ikut ke perangkat lain (HP/laptop),
 * sedangkan localStorage dipakai supaya halaman tidak "berkedip" saat dibuka.
 *
 * Murni aditif: user lama otomatis 'light' (tampilan sekarang tidak berubah).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('users', 'theme_mode')) {
            Schema::table('users', function (Blueprint $table) {
                $table->string('theme_mode', 10)->default('light')->after('theme_color');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('users', 'theme_mode')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('theme_mode');
            });
        }
    }
};
