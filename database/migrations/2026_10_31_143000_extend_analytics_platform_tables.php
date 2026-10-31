<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('analytics_events', function (Blueprint $table): void {
            if (! Schema::hasColumn('analytics_events', 'occurred_at')) {
                $table->timestamp('occurred_at')->nullable()->after('platform');
            }
            if (! Schema::hasColumn('analytics_events', 'screen_name')) {
                $table->string('screen_name', 120)->nullable()->after('occurred_at');
            }
            if (! Schema::hasColumn('analytics_events', 'page_path')) {
                $table->string('page_path', 500)->nullable()->after('screen_name');
            }
            if (! Schema::hasColumn('analytics_events', 'app_version')) {
                $table->string('app_version', 40)->nullable()->after('page_path');
            }
            if (! Schema::hasColumn('analytics_events', 'device_category')) {
                $table->string('device_category', 40)->nullable()->after('app_version');
            }
            if (! Schema::hasColumn('analytics_events', 'os_name')) {
                $table->string('os_name', 40)->nullable()->after('device_category');
            }
            if (! Schema::hasColumn('analytics_events', 'browser')) {
                $table->string('browser', 80)->nullable()->after('os_name');
            }
            if (! Schema::hasColumn('analytics_events', 'duration_ms')) {
                $table->unsignedInteger('duration_ms')->nullable()->after('browser');
            }
            if (! Schema::hasColumn('analytics_events', 'metadata')) {
                $table->json('metadata')->nullable()->after('duration_ms');
            }
        });

        $this->safeIndex('analytics_events', 'analytics_events_occurred_at_idx', 'occurred_at');
        $this->safeIndex('analytics_events', 'analytics_events_type_occurred_idx', 'event_type, occurred_at');
        $this->safeIndex('analytics_events', 'analytics_events_user_occurred_idx', 'user_id, occurred_at');
        $this->safeIndex('analytics_events', 'analytics_events_session_id_idx', 'session_id');
        $this->safeIndex('analytics_events', 'analytics_events_post_type_idx', 'post_id, event_type');

        if (! Schema::hasTable('analytics_sessions')) {
            Schema::create('analytics_sessions', function (Blueprint $table): void {
                $table->id();
                $table->string('session_id', 120)->unique();
                $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
                $table->string('platform', 20)->nullable();
                $table->string('device_category', 40)->nullable();
                $table->string('app_version', 40)->nullable();
                $table->dateTime('started_at');
                $table->dateTime('last_activity_at');
                $table->dateTime('ended_at')->nullable();
                $table->unsignedInteger('duration_seconds')->nullable();
                $table->string('entry_screen', 120)->nullable();
                $table->string('exit_screen', 120)->nullable();
                $table->timestamps();

                $table->index(['started_at', 'platform']);
                $table->index(['user_id', 'started_at']);
                $table->index('last_activity_at');
            });
        }

        if (! Schema::hasTable('analytics_daily_summaries')) {
            Schema::create('analytics_daily_summaries', function (Blueprint $table): void {
                $table->id();
                $table->date('summary_date');
                $table->string('platform', 20)->nullable();
                $table->unsignedInteger('total_events')->default(0);
                $table->unsignedInteger('unique_users')->default(0);
                $table->unsignedInteger('unique_sessions')->default(0);
                $table->unsignedInteger('app_opens')->default(0);
                $table->unsignedInteger('screen_views')->default(0);
                $table->unsignedInteger('post_views')->default(0);
                $table->unsignedInteger('engagements')->default(0);
                $table->json('payload')->nullable();
                $table->timestamps();

                $table->unique(['summary_date', 'platform'], 'analytics_daily_summaries_date_platform_uq');
            });
        }

        if (! Schema::hasTable('analytics_funnel_definitions')) {
            Schema::create('analytics_funnel_definitions', function (Blueprint $table): void {
                $table->id();
                $table->string('key', 80)->unique();
                $table->string('name', 160);
                $table->text('description')->nullable();
                $table->unsignedInteger('max_window_hours')->default(168);
                $table->boolean('is_sequential')->default(true);
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('analytics_funnel_steps')) {
            Schema::create('analytics_funnel_steps', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('funnel_id')->constrained('analytics_funnel_definitions')->cascadeOnDelete();
                $table->unsignedSmallInteger('step_order');
                $table->string('name', 160);
                $table->string('event_type', 100);
                $table->string('resource_type', 50)->nullable();
                $table->timestamps();

                $table->unique(['funnel_id', 'step_order']);
            });
        }

        if (! Schema::hasTable('analytics_engagement_score_weights')) {
            Schema::create('analytics_engagement_score_weights', function (Blueprint $table): void {
                $table->id();
                $table->string('event_key', 80)->unique();
                $table->string('label', 120);
                $table->unsignedSmallInteger('points')->default(1);
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('analytics_alert_rules')) {
            Schema::create('analytics_alert_rules', function (Blueprint $table): void {
                $table->id();
                $table->string('key', 80)->unique();
                $table->string('name', 160);
                $table->string('metric_key', 80);
                $table->string('comparison', 40)->default('previous_period');
                $table->unsignedSmallInteger('lookback_days')->default(7);
                $table->decimal('threshold_percent', 8, 2)->default(30);
                $table->unsignedInteger('min_volume')->default(20);
                $table->string('direction', 20)->default('either');
                $table->string('severity', 20)->default('medium');
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('analytics_anomaly_alerts')) {
            Schema::create('analytics_anomaly_alerts', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('rule_id')->nullable()->constrained('analytics_alert_rules')->nullOnDelete();
                $table->string('title', 200);
                $table->text('description')->nullable();
                $table->string('metric_key', 80);
                $table->decimal('previous_value', 14, 2)->default(0);
                $table->decimal('current_value', 14, 2)->default(0);
                $table->decimal('percent_change', 10, 2)->default(0);
                $table->string('severity', 20)->default('medium');
                $table->string('status', 20)->default('new');
                $table->string('fingerprint', 120)->unique();
                $table->dateTime('detected_at');
                $table->foreignId('acknowledged_by')->nullable()->constrained('users')->nullOnDelete();
                $table->dateTime('acknowledged_at')->nullable();
                $table->dateTime('resolved_at')->nullable();
                $table->json('context')->nullable();
                $table->timestamps();

                $table->index(['status', 'detected_at']);
                $table->index(['severity', 'detected_at']);
            });
        }

        if (! Schema::hasTable('analytics_exports')) {
            Schema::create('analytics_exports', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('requested_by')->constrained('users')->cascadeOnDelete();
                $table->string('report_type', 80);
                $table->string('format', 20);
                $table->string('status', 20)->default('pending');
                $table->json('filters')->nullable();
                $table->json('columns')->nullable();
                $table->string('disk_path')->nullable();
                $table->string('download_name')->nullable();
                $table->text('error_message')->nullable();
                $table->dateTime('ready_at')->nullable();
                $table->timestamps();

                $table->index(['requested_by', 'created_at']);
                $table->index('status');
            });
        }

        Schema::table('users', function (Blueprint $table): void {
            if (! Schema::hasColumn('users', 'region')) {
                $table->string('region', 120)->nullable()->after('about');
            }
            if (! Schema::hasColumn('users', 'province')) {
                $table->string('province', 120)->nullable()->after('region');
            }
            if (! Schema::hasColumn('users', 'city')) {
                $table->string('city', 120)->nullable()->after('province');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            foreach (['city', 'province', 'region'] as $column) {
                if (Schema::hasColumn('users', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::dropIfExists('analytics_exports');
        Schema::dropIfExists('analytics_anomaly_alerts');
        Schema::dropIfExists('analytics_alert_rules');
        Schema::dropIfExists('analytics_engagement_score_weights');
        Schema::dropIfExists('analytics_funnel_steps');
        Schema::dropIfExists('analytics_funnel_definitions');
        Schema::dropIfExists('analytics_daily_summaries');
        Schema::dropIfExists('analytics_sessions');
    }

    private function safeIndex(string $table, string $name, string $columns): void
    {
        try {
            DB::statement("CREATE INDEX `{$name}` ON `{$table}` ({$columns})");
        } catch (Throwable) {
            // Index may already exist from a partial previous run.
        }
    }
};
