<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Controllers;

use App\Modules\Identity\Application\Actions\UpdateCustomerProfileAction;
use App\Modules\Identity\Domain\Models\User;
use App\Modules\Identity\Http\Requests\UpdateCustomerProfileRequest;
use App\Modules\Identity\Http\Resources\CustomerResource;
use App\Modules\Shared\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/**
 * @group Customer - Profile & Addresses
 */
class CustomerProfileController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return ApiResponse::success(new CustomerResource($user->load('customerProfile')));
    }

    public function update(UpdateCustomerProfileRequest $request, UpdateCustomerProfileAction $action): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $updated = $action->execute($user, $request->toDTO());

        return ApiResponse::success(new CustomerResource($updated));
    }
}
