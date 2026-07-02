<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        DB::table('categories')->updateOrInsert(
            ['slug' => 'iec'],
            [
                'name' => 'Information, Education, and Communication (IEC)',
                'created_at' => $now,
                'updated_at' => $now,
            ],
        );
    }

    public function down(): void
    {
        DB::table('categories')->where('slug', 'iec')->delete();
    }
};
