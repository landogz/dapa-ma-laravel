<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('song_contest_entries');

        Schema::create('song_contests', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('description')->nullable();
            $table->text('rules')->nullable();
            $table->string('theme')->nullable();
            $table->string('allowed_entry_types')->default('both'); // song | playlist | both
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

        Schema::create('song_contest_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('song_contest_id')->constrained('song_contests')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('title');
            $table->string('artist_name');
            $table->string('entry_type')->default('song'); // song | playlist
            $table->text('description')->nullable();
            $table->longText('lyrics')->nullable();
            $table->string('media_url')->nullable();
            $table->string('cover_image_url')->nullable();
            $table->string('region')->nullable();
            $table->string('status')->default('pending'); // pending | approved | rejected | finalist | winner
            $table->text('admin_notes')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->index(['song_contest_id', 'status']);
            $table->unique(['song_contest_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('song_contest_entries');
        Schema::dropIfExists('song_contests');
    }
};
