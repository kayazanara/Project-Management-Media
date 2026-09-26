<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('project_tasks', function (Blueprint $table) {
            $table->integer('weight')->default(0)->after('title');
            $table->boolean('is_approved')->default(false)->after('weight');
            $table->timestamp('scored_at')->nullable()->after('is_approved');
        });
    }

    public function down(): void
    {
        Schema::table('project_tasks', function (Blueprint $table) {
            $table->dropColumn(['weight', 'is_approved', 'scored_at']);
        });
    }
};