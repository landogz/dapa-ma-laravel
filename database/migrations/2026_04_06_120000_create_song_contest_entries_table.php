<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('song_contest_entries', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('artist_name');
            $table->string('entry_type')->default('song'); // song | playlist
            $table->text('description')->nullable();
            $table->longText('lyrics')->nullable();
            $table->string('media_url')->nullable();
            $table->string('cover_image_url')->nullable();
            $table->unsignedSmallInteger('contest_year')->nullable();
            $table->string('award')->default('Entry');
            $table->string('theme')->nullable();
            $table->string('region')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['entry_type', 'is_active']);
            $table->index(['contest_year', 'award']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('song_contest_entries');
    }
};
