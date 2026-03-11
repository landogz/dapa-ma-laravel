<?php

namespace App\Http\Controllers\API\Admin;

use App\Http\Controllers\Controller;
use App\Services\AnalyticsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class AnalyticsAdminController extends Controller
{
    public function __construct(
        private readonly AnalyticsService $analyticsService,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $days = $this->resolveDaysFromRange($request->query('range', '7'));

        return response()->json([
            'status' => true,
            'data'   => $this->analyticsService->dashboard($days),
        ]);
    }

    public function export(Request $request): Response
    {
        $days     = $this->resolveDaysFromRange($request->query('range', '30'));
        $csv      = $this->analyticsService->exportCsv($days);
        $filename = 'dape-ma-analytics-' . now()->format('Y-m-d') . '.csv';

        return response($csv, 200, [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }

    private function resolveDaysFromRange(string $range): int
    {
        return match ($range) {
            'today' => 1,
            '7'     => 7,
            default => 30,
        };
    }
}
