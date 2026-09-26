<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->timestamp('scored_at')->nullable()->after('completed_at');
            $table->timestamp('released_at')->nullable()->after('scored_at');
            $table->timestamp('approved_by_pembina_at')->nullable()->after('released_at');
            $table->timestamp('approved_by_ketua_at')->nullable()->after('approved_by_pembina_at');
            $table->boolean('is_overdue')->default(false)->after('approved_by_ketua_at');
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn([
                'scored_at',
                'released_at',
                'approved_by_pembina_at',
                'approved_by_ketua_at',
                'is_overdue',
            ]);
        });
    }
};