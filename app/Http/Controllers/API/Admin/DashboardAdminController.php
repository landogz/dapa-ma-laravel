<?php

namespace App\Http\Controllers\API\Admin;

use App\Http\Controllers\Controller;
use App\Models\AppTranslation;
use App\Models\HopeEvent;
use App\Models\Post;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class DashboardAdminController extends Controller
{
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
            ],
            [
                'id' => 'care_checks',
                'label' => 'Care Checks submitted this week',
                'count' => $careChecksRecent,
                'href' => '/admin/care-checks',
                'icon' => 'fas fa-clipboard-check',
            ],
            [
                'id' => 'untranslated',
                'label' => 'Untranslated app strings',
                'count' => $untranslated,
                'href' => '/admin/app-translations',
                'icon' => 'fas fa-language',
            ],
            [
                'id' => 'hope_events',
                'label' => 'Hope Events happening this week',
                'count' => $hopeEventsThisWeek,
                'href' => '/admin/hope-events',
                'icon' => 'fas fa-calendar-days',
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
}
