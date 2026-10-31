<?php

namespace Database\Seeders;

use App\Models\AppOption;
use App\Support\AppOptionGroup;
use App\Support\ContentWorkflow\ContentWorkflowStatus;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;

class AppOptionSeeder extends Seeder
{
    public function run(): void
    {
        $hasWorkflow = Schema::hasColumn('app_options', 'workflow_status');

        foreach (AppOptionGroup::seedRows() as $row) {
            $payload = $row;
            if ($hasWorkflow) {
                $payload['workflow_status'] = ContentWorkflowStatus::PUBLISHED;
            }

            AppOption::query()->updateOrCreate(
                [
                    'group' => $row['group'],
                    'key' => $row['key'],
                ],
                $payload,
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
