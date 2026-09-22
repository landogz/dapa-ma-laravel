<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('care_support_resources', function (Blueprint $table) {
            $table->id();
            $table->string('category', 32); // hotline|counseling|crisis_resource|crisis_emergency
            $table->string('title_en');
            $table->string('title_tl');
            $table->string('role_en')->nullable(); // Counselors / Volunteers
            $table->string('role_tl')->nullable();
            $table->text('description_en')->nullable();
            $table->text('description_tl')->nullable();
            $table->string('meta_en')->nullable(); // 24/7 | Free | Confidential
            $table->string('meta_tl')->nullable();
            $table->string('phone')->nullable(); // may contain multiple: "0945… | #33733"
            $table->string('web_url')->nullable();
            $table->string('logo_url')->nullable();
            $table->string('logo_initial', 4)->nullable(); // letter avatar fallback
            $table->string('icon_key', 32)->nullable(); // info|plan|tips|friend
            $table->json('body_en')->nullable(); // modal paragraphs
            $table->json('body_tl')->nullable();
            $table->boolean('is_emergency')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['category', 'is_active', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('care_support_resources');
    }
};
