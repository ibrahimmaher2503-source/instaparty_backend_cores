<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Controllers;

use App\Modules\Identity\Application\Actions\ChangeVendorPasswordAction;
use App\Modules\Identity\Application\Actions\UpdateVendorEmailAction;
use App\Modules\Identity\Application\Actions\UpdateVendorPhoneAction;
use App\Modules\Identity\Http\Requests\ChangeVendorPasswordRequest;
use App\Modules\Identity\Http\Requests\UpdateVendorEmailRequest;
use App\Modules\Identity\Http\Requests\UpdateVendorPhoneRequest;
use App\Modules\Shared\Http\ApiResponse;
use Illuminate\Http\JsonResponse;

/**
 * @group Vendor - Account
 */
class VendorAccountController
{
    public function changePassword(ChangeVendorPasswordRequest $request, ChangeVendorPasswordAction $action): JsonResponse
    {
        $action->execute($request->user(), $request->validated('current_password'), $request->validated('new_password'));

        return ApiResponse::success(['message' => 'password_updated']);
    }

    public function updateEmail(UpdateVendorEmailRequest $request, UpdateVendorEmailAction $action): JsonResponse
    {
        $action->execute($request->user(), $request->validated('email'));

        return ApiResponse::success(['message' => 'email_updated']);
    }

    public function updatePhone(UpdateVendorPhoneRequest $request, UpdateVendorPhoneAction $action): JsonResponse
    {
        $action->execute($request->user(), $request->validated('phone_e164'));

        return ApiResponse::success(['message' => 'phone_updated']);
    }
}
