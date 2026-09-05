<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trainings', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('category')->default('Preventive Education');
            $table->string('region')->nullable();
            $table->string('venue')->nullable();
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->string('schedule_notes')->nullable();
            $table->string('organizer')->nullable();
            $table->string('contact')->nullable();
            $table->string('registration_url')->nullable();
            $table->unsignedInteger('slots')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['category', 'region']);
            $table->index(['start_date', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trainings');
    }
};
