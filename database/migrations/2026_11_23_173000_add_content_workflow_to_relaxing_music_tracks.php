<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('relaxing_music_tracks')) {
            return;
        }

        Schema::table('relaxing_music_tracks', function (Blueprint $table): void {
            if (! Schema::hasColumn('relaxing_music_tracks', 'workflow_status')) {
                $table->string('workflow_status', 32)->default('published')->after('id');
                $table->index('workflow_status');
            }

            if (! Schema::hasColumn('relaxing_music_tracks', 'review_notes')) {
                $table->text('review_notes')->nullable();
            }

            if (! Schema::hasColumn('relaxing_music_tracks', 'authored_by')) {
                $table->foreignId('authored_by')->nullable()->constrained('users')->nullOnDelete();
            }

            if (! Schema::hasColumn('relaxing_music_tracks', 'submitted_at')) {
                $table->timestamp('submitted_at')->nullable();
            }

            if (! Schema::hasColumn('relaxing_music_tracks', 'reviewed_by')) {
                $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            }

            if (! Schema::hasColumn('relaxing_music_tracks', 'reviewed_at')) {
                $table->timestamp('reviewed_at')->nullable();
            }

            if (! Schema::hasColumn('relaxing_music_tracks', 'pending_revision')) {
                $table->json('pending_revision')->nullable();
            }

            if (! Schema::hasColumn('relaxing_music_tracks', 'revision_status')) {
                $table->string('revision_status', 32)->nullable();
            }
        });

        DB::table('relaxing_music_tracks')
            ->whereNull('workflow_status')
            ->orWhere('workflow_status', '')
            ->update(['workflow_status' => 'published']);
    }

    public function down(): void
    {
        if (! Schema::hasTable('relaxing_music_tracks')) {
            return;
        }

        Schema::table('relaxing_music_tracks', function (Blueprint $table): void {
            if (Schema::hasColumn('relaxing_music_tracks', 'reviewed_by')) {
                $table->dropConstrainedForeignId('reviewed_by');
            }
            if (Schema::hasColumn('relaxing_music_tracks', 'authored_by')) {
                $table->dropConstrainedForeignId('authored_by');
            }

            $drop = [];
            foreach (['workflow_status', 'review_notes', 'submitted_at', 'reviewed_at', 'pending_revision', 'revision_status'] as $col) {
                if (Schema::hasColumn('relaxing_music_tracks', $col)) {
                    $drop[] = $col;
                }
            }
            if ($drop !== []) {
                $table->dropColumn($drop);
            }
        });
    }
};
