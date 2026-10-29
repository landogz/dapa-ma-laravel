<?php

namespace App\Http\Controllers\API\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\IndexAuditLogRequest;
use App\Models\AuditLog;
use App\Repositories\Audit\AuditLogRepository;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AuditLogAdminController extends Controller
{
    public function __construct(
        private readonly AuditLogRepository $auditLogRepository,
    ) {
    }

    public function index(IndexAuditLogRequest $request): JsonResponse
    {
        $filters = $request->validated();
        $perPage = min(max((int) ($filters['per_page'] ?? 25), 1), 100);

        return response()->json([
            'status' => true,
            'message' => 'Audit logs fetched.',
            'data' => $this->auditLogRepository->paginate($filters, $perPage),
            'summary' => $this->auditLogRepository->summary($filters),
        ]);
    }

    public function summary(IndexAuditLogRequest $request): JsonResponse
    {
        return response()->json([
            'status' => true,
            'message' => 'Audit summary fetched.',
            'data' => $this->auditLogRepository->summary($request->validated()),
        ]);
    }

    public function show(AuditLog $auditLog): JsonResponse
    {
        return response()->json([
            'status' => true,
            'message' => 'Audit log fetched.',
            'data' => $auditLog,
        ]);
    }

    public function export(IndexAuditLogRequest $request): StreamedResponse
    {
        $rows = $this->auditLogRepository->export($request->validated());
        $filename = 'dape-ma-audit-trail-'.now()->format('Y-m-d-His').'.csv';

        return response()->streamDownload(function () use ($rows): void {
            $out = fopen('php://output', 'w');
            if ($out === false) {
                return;
            }

            fputcsv($out, [
                'id',
                'created_at',
                'actor_name',
                'actor_email',
                'actor_role',
                'category',
                'action',
                'description',
                'subject_type',
                'subject_id',
                'subject_label',
                'ip_address',
            ]);

            foreach ($rows as $row) {
                fputcsv($out, [
                    $row->id,
                    optional($row->created_at)?->toDateTimeString(),
                    $row->actor_name,
                    $row->actor_email,
                    $row->actor_role,
                    $row->category,
                    $row->action,
                    $row->description,
                    $row->subject_type,
                    $row->subject_id,
                    $row->subject_label,
                    $row->ip_address,
                ]);
            }

            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
}
