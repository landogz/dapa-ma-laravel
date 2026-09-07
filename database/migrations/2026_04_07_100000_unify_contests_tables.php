<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contests', function (Blueprint $table) {
            $table->id();
            $table->string('category'); // song | poster | video
            $table->string('title');
            $table->text('description')->nullable();
            $table->text('rules')->nullable();
            $table->string('theme')->nullable();
            $table->string('allowed_entry_types')->nullable(); // song | playlist | both (song category)
            $table->unsignedSmallInteger('contest_year')->nullable();
            $table->date('submission_starts_at')->nullable();
            $table->date('submission_ends_at')->nullable();
            $table->string('status')->default('draft'); // draft | open | closed | completed
            $table->string('cover_image_url')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['category', 'status', 'is_active']);
            $table->index('contest_year');
        });

        Schema::create('contest_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contest_id')->constrained('contests')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('title');
            $table->string('creator_name');
            $table->string('entry_type')->nullable(); // song | playlist
            $table->text('description')->nullable();
            $table->longText('lyrics')->nullable();
            $table->string('media_url')->nullable();
            $table->string('cover_image_url')->nullable();
            $table->string('poster_image_url')->nullable();
            $table->string('video_url')->nullable();
            $table->string('thumbnail_url')->nullable();
            $table->string('region')->nullable();
            $table->string('status')->default('pending');
            $table->text('admin_notes')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->index(['contest_id', 'status']);
            $table->unique(['contest_id', 'user_id']);
        });

        $this->migrateLegacyData();

        Schema::dropIfExists('song_contest_entries');
        Schema::dropIfExists('song_contests');
        Schema::dropIfExists('poster_contest_entries');
        Schema::dropIfExists('poster_contests');
        Schema::dropIfExists('video_contest_entries');
        Schema::dropIfExists('video_contests');
    }

    public function down(): void
    {
        Schema::dropIfExists('contest_entries');
        Schema::dropIfExists('contests');
    }

    private function migrateLegacyData(): void
    {
        $map = [];

        if (Schema::hasTable('song_contests')) {
            foreach (DB::table('song_contests')->orderBy('id')->get() as $row) {
                $newId = DB::table('contests')->insertGetId([
                    'category' => 'song',
                    'title' => $row->title,
                    'description' => $row->description,
                    'rules' => $row->rules,
                    'theme' => $row->theme,
                    'allowed_entry_types' => $row->allowed_entry_types ?? 'both',
                    'contest_year' => $row->contest_year,
                    'submission_starts_at' => $row->submission_starts_at,
                    'submission_ends_at' => $row->submission_ends_at,
                    'status' => $row->status,
                    'cover_image_url' => $row->cover_image_url,
                    'is_active' => $row->is_active,
                    'created_at' => $row->created_at,
                    'updated_at' => $row->updated_at,
                ]);
                $map['song'][$row->id] = $newId;
            }

            if (Schema::hasTable('song_contest_entries')) {
                foreach (DB::table('song_contest_entries')->orderBy('id')->get() as $entry) {
                    $contestId = $map['song'][$entry->song_contest_id] ?? null;
                    if (! $contestId) {
                        continue;
                    }
                    DB::table('contest_entries')->insert([
                        'contest_id' => $contestId,
                        'user_id' => $entry->user_id,
                        'title' => $entry->title,
                        'creator_name' => $entry->artist_name,
                        'entry_type' => $entry->entry_type,
                        'description' => $entry->description,
                        'lyrics' => $entry->lyrics,
                        'media_url' => $entry->media_url,
                        'cover_image_url' => $entry->cover_image_url,
                        'region' => $entry->region,
                        'status' => $entry->status,
                        'admin_notes' => $entry->admin_notes,
                        'reviewed_by' => $entry->reviewed_by,
                        'reviewed_at' => $entry->reviewed_at,
                        'created_at' => $entry->created_at,
                        'updated_at' => $entry->updated_at,
                    ]);
                }
            }
        }

        if (Schema::hasTable('poster_contests')) {
            foreach (DB::table('poster_contests')->orderBy('id')->get() as $row) {
                $newId = DB::table('contests')->insertGetId([
                    'category' => 'poster',
                    'title' => $row->title,
                    'description' => $row->description,
                    'rules' => $row->rules,
                    'theme' => $row->theme,
                    'allowed_entry_types' => null,
                    'contest_year' => $row->contest_year,
                    'submission_starts_at' => $row->submission_starts_at,
                    'submission_ends_at' => $row->submission_ends_at,
                    'status' => $row->status,
                    'cover_image_url' => $row->cover_image_url,
                    'is_active' => $row->is_active,
                    'created_at' => $row->created_at,
                    'updated_at' => $row->updated_at,
                ]);
                $map['poster'][$row->id] = $newId;
            }

            if (Schema::hasTable('poster_contest_entries')) {
                foreach (DB::table('poster_contest_entries')->orderBy('id')->get() as $entry) {
                    $contestId = $map['poster'][$entry->poster_contest_id] ?? null;
                    if (! $contestId) {
                        continue;
                    }
                    DB::table('contest_entries')->insert([
                        'contest_id' => $contestId,
                        'user_id' => $entry->user_id,
                        'title' => $entry->title,
                        'creator_name' => $entry->creator_name,
                        'description' => $entry->description,
                        'poster_image_url' => $entry->poster_image_url,
                        'cover_image_url' => $entry->poster_image_url,
                        'region' => $entry->region,
                        'status' => $entry->status,
                        'admin_notes' => $entry->admin_notes,
                        'reviewed_by' => $entry->reviewed_by,
                        'reviewed_at' => $entry->reviewed_at,
                        'created_at' => $entry->created_at,
                        'updated_at' => $entry->updated_at,
                    ]);
                }
            }
        }

        if (Schema::hasTable('video_contests')) {
            foreach (DB::table('video_contests')->orderBy('id')->get() as $row) {
                $newId = DB::table('contests')->insertGetId([
                    'category' => 'video',
                    'title' => $row->title,
                    'description' => $row->description,
                    'rules' => $row->rules,
                    'theme' => $row->theme,
                    'allowed_entry_types' => null,
                    'contest_year' => $row->contest_year,
                    'submission_starts_at' => $row->submission_starts_at,
                    'submission_ends_at' => $row->submission_ends_at,
                    'status' => $row->status,
                    'cover_image_url' => $row->cover_image_url,
                    'is_active' => $row->is_active,
                    'created_at' => $row->created_at,
                    'updated_at' => $row->updated_at,
                ]);
                $map['video'][$row->id] = $newId;
            }

            if (Schema::hasTable('video_contest_entries')) {
                foreach (DB::table('video_contest_entries')->orderBy('id')->get() as $entry) {
                    $contestId = $map['video'][$entry->video_contest_id] ?? null;
                    if (! $contestId) {
                        continue;
                    }
                    DB::table('contest_entries')->insert([
                        'contest_id' => $contestId,
                        'user_id' => $entry->user_id,
                        'title' => $entry->title,
                        'creator_name' => $entry->creator_name,
                        'description' => $entry->description,
                        'video_url' => $entry->video_url,
                        'thumbnail_url' => $entry->thumbnail_url,
                        'media_url' => $entry->video_url,
                        'cover_image_url' => $entry->thumbnail_url,
                        'region' => $entry->region,
                        'status' => $entry->status,
                        'admin_notes' => $entry->admin_notes,
                        'reviewed_by' => $entry->reviewed_by,
                        'reviewed_at' => $entry->reviewed_at,
                        'created_at' => $entry->created_at,
                        'updated_at' => $entry->updated_at,
                    ]);
                }
            }
        }
    }
};
