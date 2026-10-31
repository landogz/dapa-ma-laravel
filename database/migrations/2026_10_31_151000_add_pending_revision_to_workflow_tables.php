<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * @var list<string>
     */
    private array $tables = [
        'contests',
        'iec_materials',
        'app_translations',
        'care_toolkit_questions',
        'care_support_resources',
        'hope_directory_organizations',
        'hope_events',
        'legal_pages',
        'kid_listo_quotes',
        'app_options',
        'blocked_words',
        'notifications',
    ];

    public function up(): void
    {
        foreach ($this->tables as $tableName) {
            if (! Schema::hasTable($tableName)) {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table) use ($tableName): void {
                if (! Schema::hasColumn($tableName, 'pending_revision')) {
                    $table->json('pending_revision')->nullable();
                }

                if (! Schema::hasColumn($tableName, 'revision_status')) {
                    $table->string('revision_status', 32)->nullable();
                }
            });
        }
    }

    public function down(): void
    {
        foreach ($this->tables as $tableName) {
            if (! Schema::hasTable($tableName)) {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table) use ($tableName): void {
                if (Schema::hasColumn($tableName, 'pending_revision')) {
                    $table->dropColumn('pending_revision');
                }

                if (Schema::hasColumn($tableName, 'revision_status')) {
                    $table->dropColumn('revision_status');
                }
            });
        }
    }
};
