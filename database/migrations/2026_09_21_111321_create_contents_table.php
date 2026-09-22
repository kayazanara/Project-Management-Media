<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contents', function (Blueprint $table) {
            $table->id();
            $table->string('content_code')->unique(); // Contoh: DAK01, DOK01, dll.
            $table->foreignId('project_id')->constrained('projects')->onDelete('cascade');
            $table->string('title');
            $table->string('type'); // Reels, Poster, Feed, Carousel, Story, Video, Photo, Announcement, Other
            $table->string('platform'); // Instagram, TikTok, YouTube, dll.
            $table->string('file_path')->nullable();
            $table->string('file_url')->nullable();
            $table->text('caption')->nullable();
            $table->enum('status', ['Ready', 'Uploaded', 'Archived'])->default('Ready');
            $table->timestamp('uploaded_at')->nullable(); // Opsional, terisi saat status diubah ke Uploaded
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contents');
    }
};