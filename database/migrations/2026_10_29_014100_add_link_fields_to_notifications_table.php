<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            $table->string('link_type', 32)->nullable()->after('topic');
            $table->unsignedBigInteger('link_id')->nullable()->after('link_type');
            $table->index(['link_type', 'link_id']);
        });

        DB::table('notifications')
            ->whereNotNull('post_id')
            ->whereNull('link_type')
            ->update([
                'link_type' => 'post',
                'link_id' => DB::raw('post_id'),
            ]);
    }

    public function down(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            $table->dropIndex(['link_type', 'link_id']);
            $table->dropColumn(['link_type', 'link_id']);
        });
    }
};
