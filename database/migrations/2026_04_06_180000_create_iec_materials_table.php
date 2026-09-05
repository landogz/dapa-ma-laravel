<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('iec_materials', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('topic')->nullable(); // Prevention | Awareness | Youth | Family | Recovery
            $table->string('media_type')->default('gif'); // gif | image | youtube | lottie
            $table->string('media_url');
            $table->string('thumbnail_url')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['is_active', 'sort_order']);
            $table->index('media_type');
            $table->index('topic');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('iec_materials');
    }
};
