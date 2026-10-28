<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Collapse duplicate nicknames (case-insensitive) before adding the unique index.
        $duplicates = DB::table('users')
            ->select(DB::raw('LOWER(nickname) as nick_key'), DB::raw('MIN(id) as keep_id'))
            ->whereNotNull('nickname')
            ->where('nickname', '!=', '')
            ->groupBy(DB::raw('LOWER(nickname)'))
            ->havingRaw('COUNT(*) > 1')
            ->get();

        foreach ($duplicates as $row) {
            DB::table('users')
                ->whereRaw('LOWER(nickname) = ?', [$row->nick_key])
                ->where('id', '!=', $row->keep_id)
                ->update(['nickname' => null]);
        }

        Schema::table('users', function (Blueprint $table): void {
            $table->unique('nickname');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropUnique(['nickname']);
        });
    }
};
