<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Controllers;

use App\Modules\Identity\Application\Actions\AddCustomerAddressAction;
use App\Modules\Identity\Domain\Models\CustomerAddress;
use App\Modules\Identity\Domain\Models\User;
use App\Modules\Identity\Http\Requests\AddCustomerAddressRequest;
use App\Modules\Shared\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class CustomerAddressController extends Controller
{
    public function store(AddCustomerAddressRequest $request, AddCustomerAddressAction $action): JsonResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);
        $address = $action->execute($user, $request->validated());

        return ApiResponse::success([
            'id' => $address->public_id,
            'city_id' => $address->city_id,
            'label' => $address->label,
            'address_line' => $address->address_line,
            'is_default' => $address->is_default,
            'recipient_name' => $address->recipient_name,
            'recipient_phone_e164' => $address->recipient_phone_e164,
        ], status: 201);
    }

    public function destroy(Request $request, CustomerAddress $customerAddress): JsonResponse
    {
        abort_if($customerAddress->user_id !== $request->user()?->id, 403);

        $customerAddress->delete();

        return ApiResponse::success([], status: 204);
    }
}
