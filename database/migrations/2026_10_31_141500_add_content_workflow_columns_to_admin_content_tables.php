<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
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
                if (! Schema::hasColumn($tableName, 'workflow_status')) {
                    $table->string('workflow_status', 32)->default('published')->after('id');
                    $table->index('workflow_status');
                }

                if (! Schema::hasColumn($tableName, 'review_notes')) {
                    $table->text('review_notes')->nullable();
                }

                if (! Schema::hasColumn($tableName, 'authored_by')) {
                    $table->foreignId('authored_by')->nullable()->constrained('users')->nullOnDelete();
                }

                if (! Schema::hasColumn($tableName, 'submitted_at')) {
                    $table->timestamp('submitted_at')->nullable();
                }

                if (! Schema::hasColumn($tableName, 'reviewed_by')) {
                    $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
                }

                if (! Schema::hasColumn($tableName, 'reviewed_at')) {
                    $table->timestamp('reviewed_at')->nullable();
                }
            });

            DB::table($tableName)->whereNull('workflow_status')->orWhere('workflow_status', '')->update([
                'workflow_status' => 'published',
            ]);

            // Existing live rows are treated as published.
            DB::table($tableName)->where('workflow_status', 'published')->update([
                'workflow_status' => 'published',
            ]);
        }
    }

    public function down(): void
    {
        foreach ($this->tables as $tableName) {
            if (! Schema::hasTable($tableName)) {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table) use ($tableName): void {
                if (Schema::hasColumn($tableName, 'reviewed_by')) {
                    $table->dropConstrainedForeignId('reviewed_by');
                }
                if (Schema::hasColumn($tableName, 'authored_by')) {
                    $table->dropConstrainedForeignId('authored_by');
                }

                $drop = [];
                foreach (['workflow_status', 'review_notes', 'submitted_at', 'reviewed_at'] as $col) {
                    if (Schema::hasColumn($tableName, $col)) {
                        $drop[] = $col;
                    }
                }
                if ($drop !== []) {
                    $table->dropColumn($drop);
                }
            });
        }
    }
};
