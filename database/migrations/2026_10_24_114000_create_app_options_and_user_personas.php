<?php

use Database\Seeders\AppOptionSeeder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('app_options')) {
            Schema::create('app_options', function (Blueprint $table) {
                $table->id();
                $table->string('group', 64);
                $table->string('key', 80);
                $table->string('label_en');
                $table->string('label_tl');
                $table->string('subtitle_en', 500)->nullable();
                $table->string('subtitle_tl', 500)->nullable();
                $table->string('icon', 80)->nullable();
                $table->string('accent_color', 16)->nullable();
                $table->unsignedInteger('sort_order')->default(0);
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->unique(['group', 'key']);
                $table->index(['group', 'is_active', 'sort_order']);
            });
        }

        if (Schema::hasTable('users') && ! Schema::hasColumn('users', 'personas')) {
            Schema::table('users', function (Blueprint $table) {
                $table->json('personas')->nullable()->after('persona');
            });
        }

        (new AppOptionSeeder())->run();
    }

    public function down(): void
    {
        if (Schema::hasTable('users') && Schema::hasColumn('users', 'personas')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('personas');
            });
        }

        Schema::dropIfExists('app_options');
    }
};
