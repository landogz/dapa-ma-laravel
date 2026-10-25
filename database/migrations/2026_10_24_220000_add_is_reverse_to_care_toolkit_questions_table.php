<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('care_toolkit_questions', function (Blueprint $table) {
            $table->boolean('is_reverse')->default(false)->after('is_active');
        });

        DB::table('care_toolkit_questions')
            ->where('toolkit_type', 'stress')
            ->whereIn('sort_order', [4, 5])
            ->update(['is_reverse' => true]);

        DB::table('care_toolkit_questions')
            ->where('toolkit_type', 'sleep')
            ->where('sort_order', 5)
            ->update(['is_reverse' => true]);
    }

    public function down(): void
    {
        Schema::table('care_toolkit_questions', function (Blueprint $table) {
            $table->dropColumn('is_reverse');
        });
    }
};
