<?php

namespace Database\Seeders;

use App\Models\AppOption;
use App\Support\AppOptionGroup;
use Illuminate\Database\Seeder;

class AppOptionSeeder extends Seeder
{
    public function run(): void
    {
        foreach (AppOptionGroup::seedRows() as $row) {
            AppOption::query()->updateOrCreate(
                [
                    'group' => $row['group'],
                    'key' => $row['key'],
                ],
                $row,
            );
        }

        $skyKeys = collect(AppOptionGroup::seedRows())
            ->where('group', AppOptionGroup::JOURNAL_SKY)
            ->pluck('key')
            ->all();

        AppOption::query()
            ->where('group', AppOptionGroup::JOURNAL_SKY)
            ->whereNotIn('key', $skyKeys)
            ->update(['is_active' => false]);
    }
}
