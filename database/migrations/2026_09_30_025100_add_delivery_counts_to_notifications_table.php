<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            if (! Schema::hasColumn('notifications', 'recipient_count')) {
                $table->unsignedInteger('recipient_count')->default(0)->after('sent_at');
            }
            if (! Schema::hasColumn('notifications', 'inbox_count')) {
                $table->unsignedInteger('inbox_count')->default(0)->after('recipient_count');
            }
        });
    }

    public function down(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            if (Schema::hasColumn('notifications', 'inbox_count')) {
                $table->dropColumn('inbox_count');
            }
            if (Schema::hasColumn('notifications', 'recipient_count')) {
                $table->dropColumn('recipient_count');
            }
        });
    }
};
