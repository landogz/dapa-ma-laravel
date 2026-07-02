<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('daily_verses', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('day_of_year')->unique();
            $table->string('reference');
            $table->text('verse_text');
            $table->string('translation')->default('Tagalog');
            $table->string('book');
            $table->unsignedSmallInteger('chapter');
            $table->unsignedSmallInteger('verse_start');
            $table->unsignedSmallInteger('verse_end')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_verses');
    }
};
