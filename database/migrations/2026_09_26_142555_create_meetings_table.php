<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('meetings', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->date('meeting_date');
            $table->time('start_time');
            $table->time('end_time');
            $table->string('location')->nullable();
            $table->json('attendees')->nullable();
            $table->text('agenda')->nullable();
            $table->longText('discussion')->nullable();
            $table->json('decisions')->nullable();
            $table->json('action_items')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->string('status', 20)->default('draft');
            $table->timestamp('published_at')->nullable();
            $table->timestamps();

            $table->index(['meeting_date', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meetings');
    }
};