<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('app_translations', function (Blueprint $table): void {
            $table->string('icon', 80)->nullable()->after('description');
            $table->string('image_path', 255)->nullable()->after('icon');
        });
    }

    public function down(): void
    {
        Schema::table('app_translations', function (Blueprint $table): void {
            $table->dropColumn(['icon', 'image_path']);
        });
    }
};
