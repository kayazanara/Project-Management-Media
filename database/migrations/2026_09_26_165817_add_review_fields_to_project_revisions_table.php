<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('project_revisions', function (Blueprint $table) {
            // 'revision' (default, dari Manager minta revisi)
            // 'review_request' (dari Kepala Divisi ajukan review)
            $table->string('type', 20)->default('revision')->after('user_id');
            $table->string('file_url', 500)->nullable()->after('note');
        });
    }

    public function down(): void
    {
        Schema::table('project_revisions', function (Blueprint $table) {
            $table->dropColumn(['type', 'file_url']);
        });
    }
};