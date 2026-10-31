<?php

namespace App\Http\Controllers\API\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\Analytics\ProcessAnalyticsExportJob;
use App\Models\AnalyticsAnomalyAlert;
use App\Models\AnalyticsExport;
use App\Models\AnalyticsFunnelDefinition;
use App\Repositories\AnalyticsRepository;
use App\Services\AnalyticsService;
use App\Support\Analytics\AnalyticsDateRange;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AnalyticsAdminController extends Controller
{
    public function __construct(
        private readonly AnalyticsService $analyticsService,
        private readonly AnalyticsRepository $analyticsRepository,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        try {
            $range = $this->resolveRange($request);
        } catch (InvalidArgumentException $e) {
            return response()->json(['status' => false, 'message' => $e->getMessage()], 422);
        }

        return response()->json([
            'status' => true,
            'data' => $this->analyticsService->dashboard($range),
        ]);
    }

    public function export(Request $request): Response
    {
        try {
            $range = $this->resolveRange($request);
        } catch (InvalidArgumentException $e) {
            return response()->json(['status' => false, 'message' => $e->getMessage()], 422);
        }

        $csv = $this->analyticsService->exportCsv($range);
        $filename = 'dape-ma-analytics-'.$range->key.'-'.now()->format('Y-m-d').'.csv';

        return response($csv, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }

    public function users(Request $request): JsonResponse
    {
        $range = $this->resolveRange($request);
        $filters = $request->only([
            'q', 'age_min', 'age_max', 'region', 'province', 'city',
            'registered_from', 'registered_to', 'incomplete_profile',
            'inactive_days', 'platform', 'activity', 'post_id',
        ]);

        return response()->json([
            'status' => true,
            'data' => $this->analyticsRepository->filterUsers(
                $filters,
                $range,
                max(1, min(100, (int) $request->query('per_page', 25))),
            ),
        ]);
    }

    public function userShow(Request $request, int $user): JsonResponse
    {
        $range = $this->resolveRange($request);

        return response()->json([
            'status' => true,
            'data' => $this->analyticsRepository->userDetail($user, $range),
        ]);
    }

    public function postShow(Request $request, int $post): JsonResponse
    {
        $range = $this->resolveRange($request);

        return response()->json([
            'status' => true,
            'data' => $this->analyticsRepository->postDetail($post, $range),
        ]);
    }

    public function postEngagers(Request $request, int $post): JsonResponse
    {
        $range = $this->resolveRange($request);

        return response()->json([
            'status' => true,
            'data' => $this->analyticsRepository->postEngagers(
                $post,
                $range,
                $request->only(['interaction']),
                max(1, min(100, (int) $request->query('per_page', 25))),
            ),
        ]);
    }

    public function retention(Request $request): JsonResponse
    {
        $range = $this->resolveRange($request);
        $grain = in_array($request->query('grain'), ['week', 'month'], true)
            ? $request->query('grain')
            : 'week';

        return response()->json([
            'status' => true,
            'data' => $this->analyticsRepository->retentionCohorts($range, $grain),
        ]);
    }

    public function leaderboard(Request $request): JsonResponse
    {
        $range = $this->resolveRange($request);

        return response()->json([
            'status' => true,
            'data' => $this->analyticsRepository->leaderboard(
                $range,
                (string) $request->query('metric', 'engagements'),
                (int) $request->query('limit', 25),
                $request->only(['platform']),
            ),
        ]);
    }

    public function geography(Request $request): JsonResponse
    {
        $range = $this->resolveRange($request);
        $level = in_array($request->query('level'), ['region', 'province', 'city'], true)
            ? $request->query('level')
            : 'province';

        return response()->json([
            'status' => true,
            'data' => $this->analyticsRepository->geographicDistribution($range, $level),
        ]);
    }

    public function journeys(Request $request): JsonResponse
    {
        $range = $this->resolveRange($request);
        $key = (string) $request->query('funnel', 'default_content');

        $funnels = AnalyticsFunnelDefinition::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'key', 'name']);

        return response()->json([
            'status' => true,
            'data' => [
                'funnels' => $funnels,
                'report' => $this->analyticsRepository->funnelReport($key, $range),
            ],
        ]);
    }

    public function alerts(Request $request): JsonResponse
    {
        $query = AnalyticsAnomalyAlert::query()->orderByDesc('detected_at');
        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }
        if ($severity = $request->query('severity')) {
            $query->where('severity', $severity);
        }

        return response()->json([
            'status' => true,
            'data' => $query->paginate(max(1, min(100, (int) $request->query('per_page', 25)))),
        ]);
    }

    public function updateAlert(Request $request, int $alert): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['required', Rule::in(['new', 'acknowledged', 'resolved', 'dismissed'])],
        ]);

        $model = AnalyticsAnomalyAlert::query()->findOrFail($alert);
        $model->status = $validated['status'];
        if ($validated['status'] === 'acknowledged') {
            $model->acknowledged_by = $request->user()?->id;
            $model->acknowledged_at = now();
        }
        if ($validated['status'] === 'resolved') {
            $model->resolved_at = now();
        }
        $model->save();

        return response()->json([
            'status' => true,
            'message' => 'Alert updated.',
            'data' => $model,
        ]);
    }

    public function requestExport(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'report_type' => ['required', 'string', Rule::in([
                'summary', 'users', 'traffic', 'mobile', 'posts', 'user_activity',
                'sessions', 'retention', 'journeys', 'leaderboard', 'geography', 'alerts', 'complete',
            ])],
            'format' => ['required', Rule::in(['csv', 'xlsx', 'pdf'])],
            'range' => ['nullable', 'string'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'columns' => ['nullable', 'array'],
            'filters' => ['nullable', 'array'],
        ]);

        $export = AnalyticsExport::query()->create([
            'requested_by' => $request->user()->id,
            'report_type' => $validated['report_type'],
            'format' => $validated['format'],
            'status' => 'pending',
            'filters' => array_merge($validated['filters'] ?? [], [
                'range' => $validated['range'] ?? '30',
                'from' => $validated['from'] ?? null,
                'to' => $validated['to'] ?? null,
            ]),
            'columns' => $validated['columns'] ?? null,
        ]);

        ProcessAnalyticsExportJob::dispatch($export->id);

        return response()->json([
            'status' => true,
            'message' => 'Export queued.',
            'data' => $export,
        ], 202);
    }

    public function exports(Request $request): JsonResponse
    {
        $rows = AnalyticsExport::query()
            ->where('requested_by', $request->user()->id)
            ->orderByDesc('id')
            ->paginate(20);

        return response()->json(['status' => true, 'data' => $rows]);
    }

    public function downloadExport(Request $request, int $export): StreamedResponse|JsonResponse
    {
        $model = AnalyticsExport::query()
            ->where('requested_by', $request->user()->id)
            ->findOrFail($export);

        if ($model->status !== 'ready' || ! $model->disk_path || ! Storage::disk('local')->exists($model->disk_path)) {
            return response()->json(['status' => false, 'message' => 'Export is not ready.'], 409);
        }

        return Storage::disk('local')->download(
            $model->disk_path,
            $model->download_name ?: basename($model->disk_path),
        );
    }

    private function resolveRange(Request $request): AnalyticsDateRange
    {
        return AnalyticsDateRange::fromRequest([
            'range' => $request->query('range', $request->input('range', '7')),
            'from' => $request->query('from', $request->input('from')),
            'to' => $request->query('to', $request->input('to')),
        ]);
    }
}
