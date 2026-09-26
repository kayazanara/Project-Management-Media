<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('division_role', 30)->default('anggota')->after('division');
        });

        // Backfill: set division_role berdasarkan division existing
        DB::table('users')
            ->where('division', 'like', '%Pembina%')
            ->update(['division_role' => 'pembina']);

        DB::table('users')
            ->where('division', 'like', '%Ketua Media%')
            ->update(['division_role' => 'ketua_media']);

        // Sisanya otomatis 'anggota' (default)
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('division_role');
        });
    }
};