<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('posts', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('body');
            $table->foreignId('category_id')
                ->constrained()
                ->cascadeOnDelete();
            $table->string('media_url')->nullable();
            $table->string('youtube_url')->nullable();
            $table->enum('status', [
                'draft',
                'pending_review',
                'scheduled',
                'published',
                'archived',
            ])->default('draft');
            $table->timestamp('publish_date')->nullable();
            $table->foreignId('author_id')
                ->constrained('users')
                ->cascadeOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('posts');
    }
};
