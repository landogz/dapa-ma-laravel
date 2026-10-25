<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('care_check_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('check_type', 32);
            $table->unsignedSmallInteger('raw_score');
            $table->unsignedSmallInteger('max_score');
            $table->unsignedTinyInteger('percent');
            $table->string('band', 32);
            $table->string('display_tier', 16);
            $table->decimal('sleep_hours', 5, 2)->nullable();
            $table->longText('answers')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'check_type', 'created_at']);
            $table->index(['check_type', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('care_check_results');
    }
};
