<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('post_likes', function (Blueprint $table) {
            $table->string('reaction', 16)
                ->default('like')
                ->after('post_id');

            $table->index(['post_id', 'reaction']);
        });
    }

    public function down(): void
    {
        Schema::table('post_likes', function (Blueprint $table) {
            $table->dropIndex(['post_id', 'reaction']);
            $table->dropColumn('reaction');
        });
    }
};
