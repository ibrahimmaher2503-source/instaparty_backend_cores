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
