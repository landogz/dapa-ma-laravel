<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sticker_packs', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->string('cover_path')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('stickers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sticker_pack_id')->constrained('sticker_packs')->cascadeOnDelete();
            $table->string('name')->nullable();
            $table->string('image_path');
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['sticker_pack_id', 'sort_order']);
            $table->index(['sticker_pack_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stickers');
        Schema::dropIfExists('sticker_packs');
    }
};
