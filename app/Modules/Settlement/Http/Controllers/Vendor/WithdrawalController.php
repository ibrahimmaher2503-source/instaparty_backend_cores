<?php

declare(strict_types=1);

namespace App\Modules\Settlement\Http\Controllers\Vendor;

use App\Modules\Identity\Domain\Models\User;
use App\Modules\Settlement\Application\Actions\RequestWithdrawalAction;
use App\Modules\Settlement\Domain\Exceptions\ExistingPendingWithdrawalException;
use App\Modules\Settlement\Domain\Exceptions\InsufficientWalletBalanceException;
use App\Modules\Settlement\Domain\Exceptions\WithdrawalBelowMinimumException;
use App\Modules\Settlement\Http\Requests\RequestWithdrawalRequest;
use App\Modules\Settlement\Http\Resources\WithdrawalResource;
use App\Modules\Settlement\Infrastructure\Repositories\EloquentWithdrawalRepository;
use App\Modules\Shared\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class WithdrawalController extends Controller
{
    public function __construct(
        private RequestWithdrawalAction $requestWithdrawal,
        private EloquentWithdrawalRepository $withdrawalRepo,
    ) {}

    public function store(RequestWithdrawalRequest $request): JsonResponse
    {
        try {
            $withdrawal = $this->requestWithdrawal->execute($request->toDto());
        } catch (WithdrawalBelowMinimumException $e) {
            return ApiResponse::error($e->getMessage(), 422);
        } catch (InsufficientWalletBalanceException $e) {
            return ApiResponse::error($e->getMessage(), 422);
        } catch (ExistingPendingWithdrawalException $e) {
            return ApiResponse::error($e->getMessage(), 422);
        }

        return ApiResponse::success(new WithdrawalResource($withdrawal), status: 201);
    }

    public function index(Request $request): JsonResponse
    {
        $vendorProfileId = $this->vendorProfileIdForUser($request->user());
        $paginator = $this->withdrawalRepo->paginateForVendor(
            $vendorProfileId,
            $request->query('status'),
            (int) $request->query('per_page', 25),
        );

        return ApiResponse::success(
            WithdrawalResource::collection($paginator),
            meta: [
                'pagination' => [
                    'per_page' => $paginator->perPage(),
                    'next_cursor' => $paginator->nextCursor()?->encode(),
                    'prev_cursor' => $paginator->previousCursor()?->encode(),
                ],
            ],
        );
    }

    public function show(Request $request, string $publicId): JsonResponse
    {
        $vendorProfileId = $this->vendorProfileIdForUser($request->user());
        $withdrawal = $this->withdrawalRepo->findByPublicId($publicId, $vendorProfileId);

        if ($withdrawal === null) {
            return ApiResponse::error(__('settlement::settlement.errors.withdrawal_not_found'), 404);
        }

        return ApiResponse::success(new WithdrawalResource($withdrawal));
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
