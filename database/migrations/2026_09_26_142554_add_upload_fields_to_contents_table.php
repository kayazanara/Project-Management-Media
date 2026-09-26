<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contents', function (Blueprint $table) {
            $table->string('upload_type', 20)->default('content_stock')->after('project_id');
            $table->string('target_division')->nullable()->after('upload_type');
            $table->string('category')->nullable()->after('type');
            $table->string('content_type')->nullable()->after('category');
            $table->timestamp('broadcast_filled_at')->nullable()->after('uploaded_at');
        });
    }

    public function down(): void
    {
        Schema::table('contents', function (Blueprint $table) {
            $table->dropColumn([
                'upload_type',
                'target_division',
                'category',
                'content_type',
                'broadcast_filled_at',
            ]);
        });
    }
};