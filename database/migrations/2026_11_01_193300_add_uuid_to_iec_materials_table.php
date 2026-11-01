<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('iec_materials') || Schema::hasColumn('iec_materials', 'uuid')) {
            return;
        }

        Schema::table('iec_materials', function (Blueprint $table) {
            $table->uuid('uuid')->nullable()->unique()->after('id');
        });

        DB::table('iec_materials')->orderBy('id')->chunkById(100, function ($rows) {
            foreach ($rows as $row) {
                DB::table('iec_materials')->where('id', $row->id)->update([
                    'uuid' => (string) Str::uuid(),
                ]);
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('iec_materials') || ! Schema::hasColumn('iec_materials', 'uuid')) {
            return;
        }

        Schema::table('iec_materials', function (Blueprint $table) {
            $table->dropUnique(['uuid']);
            $table->dropColumn('uuid');
        });
    }
};
