<?php

namespace App\Http\Controllers\API\Admin;

use App\Http\Controllers\Controller;
use App\Models\AppTranslation;
use App\Models\DiaryEntry;
use App\Models\HopeEvent;
use App\Models\Notification;
use App\Models\Post;
use App\Models\User;
use App\Services\AnalyticsService;
use App\Support\Analytics\AnalyticsDateRange;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;

class DashboardAdminController extends Controller
{
    public function __construct(
        private readonly AnalyticsService $analyticsService,
    ) {
    }

    /**
     * Lightweight overview for every admin role (not only analytics_viewer).
     */
    public function overview(Request $request): JsonResponse
    {
        try {
            $range = $this->resolveRange($request);
        } catch (InvalidArgumentException $exception) {
            return response()->json([
                'status' => false,
                'message' => $exception->getMessage(),
                'errors' => ['range' => [$exception->getMessage()]],
            ], 422);
        }

        $payload = $this->analyticsService->dashboard($range);
        $topPosts = collect($payload['top_posts'] ?? [])->take(5)->values()->all();
        $recentEvents = collect($payload['recent_events'] ?? [])->take(8)->values()->all();

        return response()->json([
            'status' => true,
            'message' => 'Dashboard overview fetched.',
            'data' => [
                'range' => $payload['range'] ?? $range->toArray(),
                'range_days' => $payload['range_days'] ?? $range->daySpan(),
                'daily_counts' => $payload['daily_counts'] ?? [],
                'by_event_type' => $payload['by_event_type'] ?? [],
                'top_posts' => $topPosts,
                'recent_events' => $recentEvents,
                'period_comparison' => $payload['period_comparison'] ?? null,
                'funnel' => $payload['funnel'] ?? null,
            ],
        ]);
    }

    public function attention(): JsonResponse
    {
        $draftPosts = Post::query()
            ->where(function ($query): void {
                $query->whereIn('status', ['draft', 'pending_review'])
                    ->orWhere(function ($revision): void {
                        $revision->where('status', 'published')
                            ->whereIn('revision_status', ['draft', 'pending_review']);
                    });
            })
            ->count();

        // TODO: Care Checks have no review workflow yet — surface recent submissions as a soft cue.
        $careChecksRecent = 0;
        if (Schema::hasTable('care_check_results')) {
            $careChecksRecent = (int) DB::table('care_check_results')
                ->where('created_at', '>=', now()->subDays(7))
                ->count();
        }

        $untranslated = AppTranslation::query()
            ->where(function ($query): void {
                $query->whereNull('value_tl')
                    ->orWhere('value_tl', '')
                    ->orWhereColumn('value_tl', 'value_en');
            })
            ->count();

        $weekStart = Carbon::now()->startOfWeek()->toDateString();
        $weekEnd = Carbon::now()->endOfWeek()->toDateString();
        $hopeEventsThisWeek = HopeEvent::query()
            ->where('is_active', true)
            ->whereBetween('start_date', [$weekStart, $weekEnd])
            ->count();

        $pendingContentReview = $this->pendingWorkflowReviewTotal();

        $items = array_values(array_filter([
            [
                'id' => 'draft_posts',
                'label' => 'Unpublished draft posts',
                'count' => $draftPosts,
                'href' => '/admin/posts',
                'icon' => 'fas fa-file-pen',
                'tone' => 'amber',
            ],
            $pendingContentReview > 0 ? [
                'id' => 'pending_content_review',
                'label' => 'Content awaiting publisher review',
                'count' => $pendingContentReview,
                'href' => '/admin/dashboard',
                'icon' => 'fas fa-clock',
                'tone' => 'amber',
            ] : null,
            [
                'id' => 'care_checks',
                'label' => 'Care Checks submitted this week',
                'count' => $careChecksRecent,
                'href' => '/admin/care-checks',
                'icon' => 'fas fa-clipboard-check',
                'tone' => 'amber',
            ],
            [
                'id' => 'untranslated',
                'label' => 'Untranslated app strings',
                'count' => $untranslated,
                'href' => '/admin/app-translations',
                'icon' => 'fas fa-language',
                'tone' => 'default',
            ],
            [
                'id' => 'hope_events',
                'label' => 'Hope Events happening this week',
                'count' => $hopeEventsThisWeek,
                'href' => '/admin/hope-events',
                'icon' => 'fas fa-calendar-days',
                'tone' => 'default',
            ],
        ], static fn (?array $item): bool => is_array($item) && (int) $item['count'] > 0));

        return response()->json([
            'status' => true,
            'message' => 'Dashboard attention items fetched.',
            'data' => [
                'items' => $items,
            ],
        ]);
    }

    public function trends(Request $request): JsonResponse
    {
        try {
            $range = $this->resolveRange($request);
        } catch (InvalidArgumentException $exception) {
            return response()->json([
                'status' => false,
                'message' => $exception->getMessage(),
                'errors' => ['range' => [$exception->getMessage()]],
            ], 422);
        }

        $sources = [
            'users' => ['model' => User::class, 'table' => 'users'],
            'posts' => ['model' => Post::class, 'table' => 'posts'],
            'notifications' => ['model' => Notification::class, 'table' => 'notifications'],
            'diary' => ['model' => DiaryEntry::class, 'table' => 'diary_entries'],
            'rehab-centers' => ['table' => 'rehab_centers'],
            'contests' => ['table' => 'contests'],
            'iec-materials' => ['table' => 'iec_materials'],
            'app-translations' => ['model' => AppTranslation::class, 'table' => 'app_translations'],
        ];

        $metrics = [];
        $sparklineDays = min(30, max(1, $range->daySpan()));

        foreach ($sources as $key => $source) {
            $table = $source['table'];

            if (! Schema::hasTable($table)) {
                $metrics[$key] = [
                    'current' => 0,
                    'previous' => 0,
                    'delta' => 0,
                    'available' => false,
                    'sparkline' => [],
                ];

                continue;
            }

            $base = isset($source['model'])
                ? $source['model']::query()
                : DB::table($table);

            $current = (int) (clone $base)
                ->where('created_at', '>=', $range->from)
                ->where('created_at', '<=', $range->to)
                ->count();
            $previous = (int) (clone $base)
                ->where('created_at', '>=', $range->previousFrom)
                ->where('created_at', '<', $range->previousTo)
                ->count();

            $sparkline = [];
            if (in_array($key, ['users', 'posts', 'notifications', 'diary'], true)) {
                $sparkline = $this->dailyCreatedCounts($base, $sparklineDays, $range->to);
            }

            $metrics[$key] = [
                'current' => $current,
                'previous' => $previous,
                'delta' => $current - $previous,
                'available' => ($current + $previous) > 0,
                'sparkline' => $sparkline,
            ];
        }

        return response()->json([
            'status' => true,
            'message' => 'Dashboard trends fetched.',
            'data' => [
                'range' => $range->toArray(),
                'label' => strtolower($range->label),
                'metrics' => $metrics,
            ],
        ]);
    }

    private function resolveRange(Request $request): AnalyticsDateRange
    {
        return AnalyticsDateRange::fromRequest([
            'range' => $request->query('range', $request->input('range', '7')),
            'from' => $request->query('from', $request->input('from')),
            'to' => $request->query('to', $request->input('to')),
        ]);
    }

    private function pendingWorkflowReviewTotal(): int
    {
        $tables = [
            'contests',
            'iec_materials',
            'app_translations',
            'care_toolkit_questions',
            'care_support_resources',
            'hope_directory_organizations',
            'hope_events',
            'kid_listo_quotes',
            'legal_pages',
            'app_options',
            'blocked_words',
            'notifications',
        ];

        $total = 0;

        foreach ($tables as $table) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'workflow_status')) {
                continue;
            }

            $total += (int) DB::table($table)
                ->where(function ($query) use ($table): void {
                    $query->where('workflow_status', 'pending_review');

                    if (Schema::hasColumn($table, 'revision_status')) {
                        $query->orWhere(function ($revision): void {
                            $revision->where('workflow_status', 'published')
                                ->where('revision_status', 'pending_review');
                        });
                    }
                })
                ->count();
        }

        return $total;
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<\Illuminate\Database\Eloquent\Model>|\Illuminate\Database\Query\Builder  $query
     * @return list<int>
     */
    private function dailyCreatedCounts($query, int $days = 7, ?Carbon $end = null): array
    {
        $endAt = ($end ?? Carbon::now())->copy()->endOfDay();
        $from = $endAt->copy()->subDays($days - 1)->startOfDay();
        $rows = (clone $query)
            ->select(DB::raw('DATE(created_at) as date'), DB::raw('count(*) as total'))
            ->where('created_at', '>=', $from)
            ->where('created_at', '<=', $endAt)
            ->groupBy(DB::raw('DATE(created_at)'))
            ->pluck('total', 'date')
            ->all();

        $series = [];
        for ($i = 0; $i < $days; $i++) {
            $date = $from->copy()->addDays($i)->toDateString();
            $series[] = (int) ($rows[$date] ?? 0);
        }

        return $series;
    }
}
