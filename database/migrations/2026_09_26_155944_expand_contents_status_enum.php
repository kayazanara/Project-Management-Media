<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $driver = DB::connection()->getDriverName();

        if ($driver === 'sqlite') {
            // SQLite: rebuild kolom status sebagai VARCHAR tanpa CHECK constraint
            // agar bisa menampung nilai baru: 'Completed'
            Schema::table('contents', function (Blueprint $table) {
                $table->string('status', 20)->default('Ready')->change();
            });
        } elseif ($driver === 'mysql' || $driver === 'mariadb') {
            // MySQL/MariaDB: ubah ENUM atau CHECK untuk tambah 'Completed'
            DB::statement("ALTER TABLE contents MODIFY COLUMN status ENUM('Ready','Uploaded','Archived','Completed') NOT NULL DEFAULT 'Ready'");
        } else {
            // Fallback: coba ->change() native Laravel
            Schema::table('contents', function (Blueprint $table) {
                $table->string('status', 20)->default('Ready')->change();
            });
        }
    }

    public function down(): void
    {
        // Tidak di-restore — CHECK lama tidak diperlukan lagi
    }
};