<?php

namespace App\Http\Controllers\API\Admin\CareCheck;

use App\Http\Controllers\Controller;
use App\Services\CareCheck\CareCheckService;
use App\Support\Analytics\AnalyticsDateRange;
use App\Support\Care\CareToolkitType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CareCheckAdminController extends Controller
{
    public function __construct(
        private readonly CareCheckService $careCheckService,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:500'],
            'check_type' => ['sometimes', 'nullable', 'string', CareToolkitType::validationRule()],
            'display_tier' => ['sometimes', 'nullable', 'string', Rule::in(['low', 'high'])],
            'search' => ['sometimes', 'nullable', 'string', 'max:200'],
            'range' => ['sometimes', 'nullable', 'string', 'max:40'],
            'from' => ['sometimes', 'nullable', 'date'],
            'to' => ['sometimes', 'nullable', 'date'],
        ]);

        $filters = [
            'check_type' => $validated['check_type'] ?? null,
            'display_tier' => $validated['display_tier'] ?? null,
            'search' => $validated['search'] ?? null,
        ];

        try {
            $range = $this->resolveRange($request);
            $filters['from'] = $range->from;
            $filters['to'] = $range->to;
        } catch (InvalidArgumentException $exception) {
            return response()->json([
                'status' => false,
                'message' => $exception->getMessage(),
            ], 422);
        }

        $paginator = $this->careCheckService->listAdmin(
            (int) ($validated['per_page'] ?? 20),
            $filters,
        );

        return response()->json([
            'status' => true,
            'message' => 'Care checks fetched successfully.',
            'data' => $paginator->through(
                fn ($item) => $this->careCheckService->formatAdmin($item),
            ),
        ]);
    }

    public function summary(Request $request): JsonResponse
    {
        try {
            $range = $this->resolveRange($request, legacyDays: true);
        } catch (InvalidArgumentException $exception) {
            return response()->json([
                'status' => false,
                'message' => $exception->getMessage(),
            ], 422);
        }

        return response()->json([
            'status' => true,
            'message' => 'Care check analytics fetched successfully.',
            'data' => $this->careCheckService->summary($range),
        ]);
    }

    public function export(Request $request): StreamedResponse|JsonResponse|Response
    {
        $validated = $request->validate([
            'check_type' => ['sometimes', 'nullable', 'string', CareToolkitType::validationRule()],
            'display_tier' => ['sometimes', 'nullable', 'string', Rule::in(['low', 'high'])],
            'search' => ['sometimes', 'nullable', 'string', 'max:200'],
            'range' => ['sometimes', 'nullable', 'string', 'max:40'],
            'from' => ['sometimes', 'nullable', 'date'],
            'to' => ['sometimes', 'nullable', 'date'],
            'format' => ['sometimes', 'nullable', 'string', Rule::in(['csv'])],
        ]);

        try {
            $range = $this->resolveRange($request);
        } catch (InvalidArgumentException $exception) {
            return response()->json([
                'status' => false,
                'message' => $exception->getMessage(),
            ], 422);
        }

        $rows = $this->careCheckService->listForExport([
            'check_type' => $validated['check_type'] ?? null,
            'display_tier' => $validated['display_tier'] ?? null,
            'search' => $validated['search'] ?? null,
            'from' => $range->from,
            'to' => $range->to,
        ]);

        $csv = $this->careCheckService->exportCsv($rows);
        $filename = sprintf(
            'dape-ma-care-checks-%s-%s.csv',
            $range->key,
            now()->toDateString(),
        );

        return response($csv, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
            'Cache-Control' => 'no-store, no-cache, must-revalidate',
        ]);
    }

    public function show(int $id): JsonResponse
    {
        $result = $this->careCheckService->showAdmin($id);

        return response()->json([
            'status' => true,
            'message' => 'Care check fetched successfully.',
            'data' => $this->careCheckService->formatAdminDetail($result),
        ]);
    }

    private function resolveRange(Request $request, bool $legacyDays = false): AnalyticsDateRange
    {
        $rangeKey = $request->query('range', $request->input('range'));

        if ($legacyDays && ($rangeKey === null || $rangeKey === '') && $request->filled('days')) {
            $days = (int) $request->query('days', 7);
            if (! in_array($days, [7, 14, 30, 60, 90], true)) {
                $days = 7;
            }
            $rangeKey = (string) $days;
        }

        return AnalyticsDateRange::fromRequest([
            'range' => $rangeKey ?: '7',
            'from' => $request->query('from', $request->input('from')),
            'to' => $request->query('to', $request->input('to')),
        ]);
    }
}
