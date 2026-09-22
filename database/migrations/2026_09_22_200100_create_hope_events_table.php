<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hope_events', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('audience', 32)->nullable(); // youth | parents | community
            $table->string('cover_path')->nullable();
            $table->string('status', 32)->default('upcoming'); // upcoming | ongoing | ended
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->string('venue')->nullable();
            $table->boolean('is_online')->default(false);
            $table->string('online_label')->nullable();
            $table->unsignedInteger('slots')->nullable();
            $table->string('registration_url')->nullable();
            $table->longText('about_text')->nullable();
            $table->json('for_you_items')->nullable();
            $table->text('who_can_join')->nullable();
            $table->json('highlights')->nullable();
            $table->longText('details_text')->nullable();
            $table->json('speakers')->nullable();
            $table->json('faqs')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['audience', 'is_active']);
            $table->index(['status', 'start_date']);
            $table->index('sort_order');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hope_events');
    }
};
