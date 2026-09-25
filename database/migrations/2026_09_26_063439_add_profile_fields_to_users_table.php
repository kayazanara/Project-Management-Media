<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->text('bio')->nullable()->after('phone');
            $table->string('jabatan')->nullable()->after('bio');
            $table->string('background_image')->nullable()->after('jabatan');
            $table->string('theme_color', 50)->default('blue-purple')->after('background_image');
            $table->json('social_links')->nullable()->after('theme_color');
            $table->integer('total_login_days')->default(0)->after('social_links');
            $table->date('last_login_date')->nullable()->after('total_login_days');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'bio',
                'jabatan',
                'background_image',
                'theme_color',
                'social_links',
                'total_login_days',
                'last_login_date',
            ]);
        });
    }
};