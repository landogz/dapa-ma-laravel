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
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

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
        $range = (string) $request->query('range', '7');
        $days = match ($range) {
            '1', 'today' => 1,
            '30', '30d', 'month' => 30,
            default => 7,
        };

        $payload = $this->analyticsService->dashboard($days);
        $topPosts = collect($payload['top_posts'] ?? [])->take(5)->values()->all();
        $recentEvents = collect($payload['recent_events'] ?? [])->take(8)->values()->all();

        return response()->json([
            'status' => true,
            'message' => 'Dashboard overview fetched.',
            'data' => [
                'range_days' => $payload['range_days'] ?? $days,
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
            ->whereIn('status', ['draft', 'pending_review'])
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

        $items = array_values(array_filter([
            [
                'id' => 'draft_posts',
                'label' => 'Unpublished draft posts',
                'count' => $draftPosts,
                'href' => '/admin/posts',
                'icon' => 'fas fa-file-pen',
                'tone' => 'amber',
            ],
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
        ], static fn (array $item): bool => (int) $item['count'] > 0));

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
        $range = (int) $request->query('range', 7);
        if (! in_array($range, [1, 7, 30], true)) {
            $range = 7;
        }

        $now = Carbon::now();
        $currentStart = $now->copy()->subDays($range);
        $previousStart = $now->copy()->subDays($range * 2);
        $previousEnd = $currentStart->copy();
        $label = match ($range) {
            1 => 'today',
            30 => 'this month',
            default => 'this week',
        };

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
                ->where('created_at', '>=', $currentStart)
                ->where('created_at', '<=', $now)
                ->count();
            $previous = (int) (clone $base)
                ->where('created_at', '>=', $previousStart)
                ->where('created_at', '<', $previousEnd)
                ->count();

            $sparkline = [];
            if (in_array($key, ['users', 'posts', 'notifications', 'diary'], true)) {
                $sparkline = $this->dailyCreatedCounts($base, 7);
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
                'range' => $range,
                'label' => $label,
                'metrics' => $metrics,
            ],
        ]);
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<\Illuminate\Database\Eloquent\Model>|\Illuminate\Database\Query\Builder  $query
     * @return list<int>
     */
    private function dailyCreatedCounts($query, int $days = 7): array
    {
        $from = Carbon::now()->subDays($days - 1)->startOfDay();
        $rows = (clone $query)
            ->select(DB::raw('DATE(created_at) as date'), DB::raw('count(*) as total'))
            ->where('created_at', '>=', $from)
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
