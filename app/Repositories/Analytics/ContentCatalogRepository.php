<?php

namespace App\Repositories\Analytics;

use App\Models\AnalyticsEvent;
use App\Models\Contest;
use App\Models\HopeDirectoryOrganization;
use App\Models\HopeEvent;
use App\Models\IecMaterial;
use App\Models\Post;
use App\Models\RehabCenter;
use App\Support\Analytics\AnalyticsDateRange;
use App\Support\Analytics\AnalyticsEventTypes;
use App\Support\ContentWorkflow\ContentWorkflowSections;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ContentCatalogRepository
{
    /**
     * Views, shares, and downloads for posts, IEC materials, events, and contests.
     *
     * @return array{
     *     totals: array<string, int>,
     *     groups: list<array<string, mixed>>
     * }
     */
    public function dashboard(AnalyticsDateRange $range): array
    {
        $resourceRows = $this->countsByResource($range);
        $postShareCounts = $this->postShareCounts($range);
        $postViewCounts = $this->postViewCounts($range);

        $iecIds = $this->idsFor($resourceRows, 'iec_material');
        $eventIds = $this->idsFor($resourceRows, 'hope_event');
        $contestIds = $this->idsFor($resourceRows, 'contest');
        $rehabIds = $this->idsFor($resourceRows, 'rehab_center');
        $hopeOrgIds = $this->idsFor($resourceRows, 'hope_directory_org');
        $postIds = array_values(array_unique(array_merge(
            array_keys($postShareCounts),
            array_keys($postViewCounts),
        )));

        $groups = [
            $this->buildGroup(
                key: 'posts',
                label: 'Posts',
                section: 'posts',
                includeDownloads: false,
                items: $this->postItems($postViewCounts, $postShareCounts, $this->titles(Post::class, $postIds)),
            ),
            $this->buildGroup(
                key: 'iec_materials',
                label: 'IEC materials',
                section: 'iec-materials',
                includeDownloads: true,
                items: $this->resourceItems(
                    $resourceRows->get('iec_material', collect()),
                    $this->titles(IecMaterial::class, $iecIds),
                    'iec-materials',
                    [
                        AnalyticsEventTypes::IEC_VIEW => 'views',
                        AnalyticsEventTypes::IEC_SHARE => 'shares',
                        AnalyticsEventTypes::IEC_DOWNLOAD => 'downloads',
                    ],
                ),
            ),
            $this->buildGroup(
                key: 'events',
                label: 'Events',
                section: 'hope-events',
                includeDownloads: false,
                items: $this->resourceItems(
                    $resourceRows->get('hope_event', collect()),
                    $this->titles(HopeEvent::class, $eventIds),
                    'hope-events',
                    [
                        AnalyticsEventTypes::EVENT_VIEW => 'views',
                        AnalyticsEventTypes::EVENT_SHARE => 'shares',
                    ],
                ),
            ),
            $this->buildGroup(
                key: 'contests',
                label: 'Contests',
                section: 'contests',
                includeDownloads: false,
                items: $this->resourceItems(
                    $resourceRows->get('contest', collect()),
                    $this->titles(Contest::class, $contestIds),
                    'contests',
                    [
                        AnalyticsEventTypes::CONTEST_VIEW => 'views',
                        AnalyticsEventTypes::CONTEST_SHARE => 'shares',
                    ],
                ),
            ),
            $this->buildGroup(
                key: 'rehab_centers',
                label: 'Rehab centers',
                section: 'rehab-centers',
                includeDownloads: true,
                viewLabel: 'Views',
                shareLabel: 'Calls',
                downloadLabel: 'Directions',
                items: $this->resourceItems(
                    $resourceRows->get('rehab_center', collect()),
                    $this->titles(RehabCenter::class, $rehabIds, 'name'),
                    'rehab-centers',
                    [
                        AnalyticsEventTypes::REHAB_VIEW => 'views',
                        AnalyticsEventTypes::REHAB_CALL => 'shares',
                        AnalyticsEventTypes::REHAB_DIRECTIONS => 'downloads',
                    ],
                ),
            ),
            $this->buildGroup(
                key: 'hope_directory',
                label: 'Hope directory',
                section: 'hope-directory',
                includeDownloads: false,
                items: $this->resourceItems(
                    $resourceRows->get('hope_directory_org', collect()),
                    $this->titles(HopeDirectoryOrganization::class, $hopeOrgIds, 'name'),
                    'hope-directory',
                    [
                        AnalyticsEventTypes::HOPE_ORG_VIEW => 'views',
                    ],
                ),
            ),
        ];

        $totals = [
            'downloads' => 0,
            'shares' => 0,
            'post_shares' => 0,
            'iec_views' => 0,
            'iec_shares' => 0,
            'iec_downloads' => 0,
            'event_views' => 0,
            'event_shares' => 0,
            'contest_views' => 0,
            'contest_shares' => 0,
            'rehab_views' => 0,
            'rehab_calls' => 0,
            'rehab_directions' => 0,
            'hope_org_views' => 0,
        ];

        foreach ($groups as $group) {
            if (in_array($group['key'], ['posts', 'iec_materials', 'events', 'contests'], true)) {
                $totals['downloads'] += (int) $group['downloads'];
                $totals['shares'] += (int) $group['shares'];
            }

            match ($group['key']) {
                'posts' => $totals['post_shares'] = (int) $group['shares'],
                'iec_materials' => [
                    $totals['iec_views'] = (int) $group['views'],
                    $totals['iec_shares'] = (int) $group['shares'],
                    $totals['iec_downloads'] = (int) $group['downloads'],
                ],
                'events' => [
                    $totals['event_views'] = (int) $group['views'],
                    $totals['event_shares'] = (int) $group['shares'],
                ],
                'contests' => [
                    $totals['contest_views'] = (int) $group['views'],
                    $totals['contest_shares'] = (int) $group['shares'],
                ],
                'rehab_centers' => [
                    $totals['rehab_views'] = (int) $group['views'],
                    $totals['rehab_calls'] = (int) $group['shares'],
                    $totals['rehab_directions'] = (int) $group['downloads'],
                ],
                'hope_directory' => $totals['hope_org_views'] = (int) $group['views'],
                default => null,
            };
        }

        return [
            'totals' => $totals,
            'groups' => $groups,
        ];
    }

    /**
     * @return Collection<string, Collection<int, Collection<int, object>>>
     */
    private function countsByResource(AnalyticsDateRange $range): Collection
    {
        return AnalyticsEvent::query()
            ->inWindow($range->from, $range->to)
            ->whereNotNull('resource_id')
            ->whereIn('resource_type', ['iec_material', 'hope_event', 'contest', 'rehab_center', 'hope_directory_org'])
            ->select(
                'resource_type',
                'resource_id',
                'event_type',
                DB::raw('count(*) as total'),
            )
            ->groupBy('resource_type', 'resource_id', 'event_type')
            ->get()
            ->groupBy('resource_type');
    }

    /**
     * @return array<int, int>
     */
    private function postShareCounts(AnalyticsDateRange $range): array
    {
        return $this->postMetricCounts($range, [AnalyticsEventTypes::SHARE]);
    }

    /**
     * @return array<int, int>
     */
    private function postViewCounts(AnalyticsDateRange $range): array
    {
        return $this->postMetricCounts($range, AnalyticsEventTypes::viewTypes());
    }

    /**
     * @param  list<string>  $eventTypes
     * @return array<int, int>
     */
    private function postMetricCounts(AnalyticsDateRange $range, array $eventTypes): array
    {
        return AnalyticsEvent::query()
            ->inWindow($range->from, $range->to)
            ->whereNotNull('post_id')
            ->whereIn('event_type', $eventTypes)
            ->select('post_id', DB::raw('count(*) as total'))
            ->groupBy('post_id')
            ->pluck('total', 'post_id')
            ->map(fn ($total) => (int) $total)
            ->all();
    }

    /**
     * @param  Collection<int, object>  $rows
     * @return list<int>
     */
    private function idsFor(Collection $grouped, string $resourceType): array
    {
        return $grouped->get($resourceType, collect())
            ->pluck('resource_id')
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @param  class-string  $model
     * @param  list<int>  $ids
     * @return array<int, string>
     */
    private function titles(string $model, array $ids, string $column = 'title'): array
    {
        if ($ids === []) {
            return [];
        }

        return $model::query()
            ->whereIn('id', $ids)
            ->pluck($column, 'id')
            ->map(fn ($title) => (string) $title)
            ->all();
    }

    /**
     * @param  array<int, int>  $views
     * @param  array<int, int>  $shares
     * @param  array<int, string>  $titles
     * @return list<array<string, mixed>>
     */
    private function postItems(array $views, array $shares, array $titles): array
    {
        $items = [];
        foreach ($titles as $id => $title) {
            $items[] = [
                'id' => (int) $id,
                'title' => $title !== '' ? $title : ('Post #'.$id),
                'views' => (int) ($views[(int) $id] ?? 0),
                'shares' => (int) ($shares[(int) $id] ?? 0),
                'downloads' => 0,
                'href' => ContentWorkflowSections::adminUrl('posts', (int) $id),
            ];
        }

        return $items;
    }

    /**
     * @param  Collection<int, object>  $rows
     * @param  array<int, string>  $titles
     * @param  array<string, string>  $metricMap
     * @return list<array<string, mixed>>
     */
    private function resourceItems(
        Collection $rows,
        array $titles,
        string $section,
        array $metricMap,
    ): array {
        $byId = [];

        foreach ($rows as $row) {
            $id = (int) $row->resource_id;
            if ($id < 1) {
                continue;
            }

            if (! isset($byId[$id])) {
                $byId[$id] = [
                    'id' => $id,
                    'title' => $titles[$id] ?? ('#'.$id),
                    'views' => 0,
                    'shares' => 0,
                    'downloads' => 0,
                    'href' => ContentWorkflowSections::adminUrl($section, $id),
                ];
            }

            $metric = $metricMap[(string) $row->event_type] ?? null;
            if ($metric !== null) {
                $byId[$id][$metric] += (int) $row->total;
            }
        }

        return array_values($byId);
    }

    /**
     * @param  list<array<string, mixed>>  $items
     * @return array<string, mixed>
     */
    private function buildGroup(
        string $key,
        string $label,
        string $section,
        bool $includeDownloads,
        array $items,
        string $viewLabel = 'Views',
        string $shareLabel = 'Shares',
        string $downloadLabel = 'Downloads',
    ): array {
        $items = array_values(array_filter(
            $items,
            fn (array $item) => ((int) $item['views'] + (int) $item['shares'] + (int) $item['downloads']) > 0,
        ));

        $views = (int) array_sum(array_column($items, 'views'));
        $shares = (int) array_sum(array_column($items, 'shares'));
        $downloads = (int) array_sum(array_column($items, 'downloads'));

        usort($items, function (array $a, array $b) {
            $scoreA = ((int) $a['shares']) * 5 + ((int) $a['downloads']) * 4 + (int) $a['views'];
            $scoreB = ((int) $b['shares']) * 5 + ((int) $b['downloads']) * 4 + (int) $b['views'];

            return $scoreB <=> $scoreA;
        });

        $items = array_slice($items, 0, 10);

        return [
            'key' => $key,
            'label' => $label,
            'section' => $section,
            'href' => ContentWorkflowSections::get($section)['path'] ?? '/admin/dashboard',
            'include_downloads' => $includeDownloads,
            'view_label' => $viewLabel,
            'share_label' => $shareLabel,
            'download_label' => $downloadLabel,
            'views' => $views,
            'shares' => $shares,
            'downloads' => $downloads,
            'items' => $items,
        ];
    }
}
