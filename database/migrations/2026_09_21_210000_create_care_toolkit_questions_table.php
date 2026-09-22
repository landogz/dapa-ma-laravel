<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('care_toolkit_questions', function (Blueprint $table) {
            $table->id();
            $table->string('toolkit_type', 32); // stress|anxiety|sleep
            $table->string('answer_type', 32)->default('likert5'); // likert5|likert4|time
            $table->text('question_en');
            $table->text('question_tl');
            $table->json('options_en')->nullable();
            $table->json('options_tl')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['toolkit_type', 'is_active', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('care_toolkit_questions');
    }
};
