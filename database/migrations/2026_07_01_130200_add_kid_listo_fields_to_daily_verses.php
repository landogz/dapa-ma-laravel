<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('daily_verses', function (Blueprint $table) {
            $table->text('kid_listo_message')->nullable()->after('verse_text');
            $table->text('kid_listo_message_en')->nullable()->after('kid_listo_message');
            $table->text('verse_text_en')->nullable()->after('kid_listo_message_en');
        });
    }

    public function down(): void
    {
        Schema::table('daily_verses', function (Blueprint $table) {
            $table->dropColumn(['kid_listo_message', 'kid_listo_message_en', 'verse_text_en']);
        });
    }
};
