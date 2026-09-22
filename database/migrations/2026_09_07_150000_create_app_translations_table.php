<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('app_translations', function (Blueprint $table): void {
            $table->id();
            $table->string('key', 191)->unique();
            $table->string('group', 64)->nullable()->index();
            $table->string('description', 255)->nullable();
            $table->text('value_en');
            $table->text('value_tl');
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('app_translations');
    }
};
