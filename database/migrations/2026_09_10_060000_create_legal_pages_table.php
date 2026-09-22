<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('legal_pages', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 64)->unique();
            $table->string('title_en');
            $table->string('title_tl');
            $table->string('subtitle_en')->nullable();
            $table->string('subtitle_tl')->nullable();
            $table->text('intro_en')->nullable();
            $table->text('intro_tl')->nullable();
            $table->longText('body_en');
            $table->longText('body_tl');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('legal_pages');
    }
};
