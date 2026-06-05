<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Controllers;

use App\Modules\Identity\Domain\Models\VendorProfile;
use App\Modules\Shared\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Vendor-portal 3.6 — the vendor's own moderation history from the
 * append-only audit log. Deliberately narrow projection: action + status
 * movement + timestamp. Raw `changes` payloads are NOT exposed (they can
 * carry admin-internal context).
 *
 * @group Vendor - Compliance
 */
class VendorComplianceAuditLogController
{
    public function __invoke(Request $request): JsonResponse
    {
        $vendor = $request->user()?->vendorProfile;

        if ($vendor === null) {
            throw new NotFoundHttpException('Vendor profile not found.');
        }

        $page = DB::table('audit_logs')
            ->where('auditable_type', VendorProfile::class)
            ->where('auditable_id', $vendor->id)
            ->orderByDesc('id')
            ->cursorPaginate(20);

        $entries = collect($page->items())->map(function (object $row): array {
            $changes = json_decode((string) ($row->changes ?? '{}'), true) ?: [];

            return [
                'action' => $row->action,
                'from_status' => $changes['from']['approval_status'] ?? $changes['from'] ?? null,
                'to_status' => $changes['to']['approval_status'] ?? $changes['to'] ?? null,
                'created_at' => $row->created_at,
            ];
        })->values();

        return ApiResponse::success(
            $entries,
            ['next_cursor' => $page->nextCursor()?->encode(), 'has_more' => $page->hasMorePages()],
        );
    }
}
