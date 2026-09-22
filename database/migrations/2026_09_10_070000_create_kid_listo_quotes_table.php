<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kid_listo_quotes', function (Blueprint $table) {
            $table->id();
            $table->text('message_en');
            $table->text('message_tl');
            $table->string('attribution')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kid_listo_quotes');
    }
};
