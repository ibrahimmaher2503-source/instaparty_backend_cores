<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Controllers;

use App\Modules\Identity\Application\Actions\AddCustomerAddressAction;
use App\Modules\Identity\Application\Actions\SetDefaultCustomerAddressAction;
use App\Modules\Identity\Application\Actions\UpdateCustomerAddressAction;
use App\Modules\Identity\Domain\Models\CustomerAddress;
use App\Modules\Identity\Domain\Models\User;
use App\Modules\Identity\Http\Requests\AddCustomerAddressRequest;
use App\Modules\Shared\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/**
 * @group Customer - Profile & Addresses
 */
class CustomerAddressController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        $addresses = CustomerAddress::query()
            ->where('customer_addresses.user_id', $user->id)
            ->join('cities', 'customer_addresses.city_id', '=', 'cities.id')
            ->select('customer_addresses.*', 'cities.governorate_id')
            ->orderByDesc('customer_addresses.is_default')
            ->orderByDesc('customer_addresses.id')
            ->get()
            ->map(fn (CustomerAddress $a) => [
                'public_id' => $a->public_id,
                'governorate_id' => $a->governorate_id,
                'city_id' => $a->city_id,
                'label' => $a->label,
                'address_line' => $a->address_line,
                'building' => $a->building,
                'floor' => $a->floor,
                'apartment' => $a->apartment,
                'landmark' => $a->landmark,
                'recipient_name' => $a->recipient_name,
                'recipient_phone_e164' => $a->recipient_phone_e164,
                'is_default' => $a->is_default,
            ]);

        return ApiResponse::success($addresses);
    }

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

    public function update(Request $request, CustomerAddress $customerAddress, UpdateCustomerAddressAction $action): JsonResponse
    {
        abort_if($customerAddress->user_id !== $request->user()?->id, 404);

        $validated = $request->validate([
            'city_id' => ['sometimes', 'integer', 'exists:cities,id'],
            'label' => ['sometimes', 'nullable', 'string', 'max:50'],
            'address_line' => ['sometimes', 'string', 'max:255'],
            'building' => ['sometimes', 'nullable', 'string', 'max:50'],
            'floor' => ['sometimes', 'nullable', 'string', 'max:20'],
            'apartment' => ['sometimes', 'nullable', 'string', 'max:20'],
            'landmark' => ['sometimes', 'nullable', 'string', 'max:255'],
            'recipient_name' => ['sometimes', 'string', 'max:120'],
            'recipient_phone_e164' => ['sometimes', 'string', 'max:20'],
        ]);

        $address = $action->execute($customerAddress, $validated);

        return ApiResponse::success([
            'id' => $address->public_id,
            'city_id' => $address->city_id,
            'label' => $address->label,
            'address_line' => $address->address_line,
            'is_default' => $address->is_default,
            'recipient_name' => $address->recipient_name,
            'recipient_phone_e164' => $address->recipient_phone_e164,
        ]);
    }

    public function setDefault(Request $request, CustomerAddress $customerAddress, SetDefaultCustomerAddressAction $action): JsonResponse
    {
        abort_if($customerAddress->user_id !== $request->user()?->id, 404);

        $address = $action->execute($customerAddress);

        return ApiResponse::success(['id' => $address->public_id, 'is_default' => true]);
    }

    public function destroy(Request $request, CustomerAddress $customerAddress): JsonResponse
    {
        abort_if($customerAddress->user_id !== $request->user()?->id, 403);

        $customerAddress->delete();

        return ApiResponse::success([], status: 204);
    }
}
