<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('analytics_events', function (Blueprint $table) {
            $table->string('resource_type', 50)->nullable()->after('post_id');
            $table->unsignedBigInteger('resource_id')->nullable()->after('resource_type');
            $table->index(['user_id', 'event_type', 'resource_type', 'resource_id'], 'analytics_user_event_resource_idx');
        });
    }

    public function down(): void
    {
        Schema::table('analytics_events', function (Blueprint $table) {
            $table->dropIndex('analytics_user_event_resource_idx');
            $table->dropColumn(['resource_type', 'resource_id']);
        });
    }
};
