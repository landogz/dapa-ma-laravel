<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rehab_centers', function (Blueprint $table) {
            $table->string('category', 64)->nullable()->after('name')->index();
        });
    }

    public function down(): void
    {
        Schema::table('rehab_centers', function (Blueprint $table) {
            $table->dropColumn('category');
        });
    }
};
