<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('app_translations')
            ->where('group', 'bible')
            ->orWhereIn('key', [
                'bibleTitle',
                'searchBibleBooks',
                'oldTestament',
                'newTestament',
                'noBibleBooksFound',
                'bibleLoadFailed',
                'bibleLanguageNote',
                'chaptersLabel',
            ])
            ->delete();
    }

    public function down(): void
    {
        // Bible labels were retired from the mobile catalog.
    }
};
