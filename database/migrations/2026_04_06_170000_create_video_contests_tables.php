<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('video_contests', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('description')->nullable();
            $table->text('rules')->nullable();
            $table->string('theme')->nullable();
            $table->unsignedSmallInteger('contest_year')->nullable();
            $table->date('submission_starts_at')->nullable();
            $table->date('submission_ends_at')->nullable();
            $table->string('status')->default('draft'); // draft | open | closed | completed
            $table->string('cover_image_url')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['status', 'is_active']);
            $table->index('contest_year');
        });

        Schema::create('video_contest_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('video_contest_id')->constrained('video_contests')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('title');
            $table->string('creator_name');
            $table->text('description')->nullable();
            $table->string('video_url');
            $table->string('thumbnail_url')->nullable();
            $table->string('region')->nullable();
            $table->string('status')->default('pending'); // pending | approved | rejected | finalist | winner
            $table->text('admin_notes')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->index(['video_contest_id', 'status']);
            $table->unique(['video_contest_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('video_contest_entries');
        Schema::dropIfExists('video_contests');
    }
};
