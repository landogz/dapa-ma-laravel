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
        Schema::create('rehab_centers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('region');
            $table->string('province');
            $table->string('address');
            $table->string('contact')->nullable();
            $table->string('website')->nullable();
            $table->timestamps();

            $table->index(['region', 'province']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('rehab_centers');
    }
};
