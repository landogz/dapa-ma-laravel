<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('diary_entries', function (Blueprint $table) {
            $table->string('sky', 64)->nullable()->after('title');
            $table->json('feelings')->nullable()->after('sky');
            $table->string('impact', 64)->nullable()->after('feelings');
            $table->longText('gratitude')->nullable()->after('impact');
            $table->longText('body_html')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('diary_entries', function (Blueprint $table) {
            $table->dropColumn(['sky', 'feelings', 'impact', 'gratitude']);
            $table->longText('body_html')->nullable(false)->change();
        });
    }
};
