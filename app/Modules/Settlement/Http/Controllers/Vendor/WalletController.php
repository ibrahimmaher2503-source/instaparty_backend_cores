<?php

declare(strict_types=1);

namespace App\Modules\Settlement\Http\Controllers\Vendor;

use App\Modules\Identity\Domain\Models\User;
use App\Modules\Settlement\Application\Services\WalletQueryService;
use App\Modules\Settlement\Http\Resources\WalletLedgerEntryResource;
use App\Modules\Settlement\Http\Resources\WalletResource;
use App\Modules\Shared\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * @group Vendor - Wallet & Settlements
 */
class WalletController extends Controller
{
    public function __construct(
        private WalletQueryService $queryService,
    ) {}

    public function show(Request $request): JsonResponse
    {
        $vendorProfileId = $this->vendorProfileIdForUser($request->user());
        $data = $this->queryService->balance($vendorProfileId);

        return ApiResponse::success(new WalletResource($data));
    }

    /**
     * @queryParam per_page int Number of entries per page (cursor pagination). Example: 25
     * @queryParam cursor string Opaque cursor for the next/previous page. Example: eyJpZCI6MTB9
     * @queryParam entry_type string Filter by ledger entry type (commission_credit, refund_debit, withdrawal_debit). Example: commission_credit
     * @queryParam from string Filter entries on or after this date (Y-m-d). Example: 2026-01-01
     * @queryParam to string Filter entries on or before this date (Y-m-d). Example: 2026-12-31
     */
    public function ledger(Request $request): JsonResponse
    {
        $vendorProfileId = $this->vendorProfileIdForUser($request->user());
        $filters = $request->only(['per_page', 'cursor', 'entry_type', 'from', 'to']);
        $paginator = $this->queryService->ledger($vendorProfileId, 'EGP', $filters);

        return ApiResponse::success(
            WalletLedgerEntryResource::collection($paginator),
            meta: [
                'pagination' => [
                    'per_page' => $paginator->perPage(),
                    'next_cursor' => $paginator->nextCursor()?->encode(),
                    'prev_cursor' => $paginator->previousCursor()?->encode(),
                ],
            ],
        );
    }

    private function vendorProfileIdForUser(?User $user): int
    {
        if ($user === null) {
            throw new NotFoundHttpException('Authenticated user not found.');
        }

        $vendorProfile = $user->vendorProfile;

        if ($vendorProfile === null) {
            throw new NotFoundHttpException('Vendor profile not found.');
        }

        return $vendorProfile->id;
    }
}
