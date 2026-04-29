<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Controllers;

use App\Modules\Identity\Application\Actions\LoginAction;
use App\Modules\Identity\Application\Actions\LogoutAction;
use App\Modules\Identity\Application\Actions\RegisterCustomerAction;
use App\Modules\Identity\Application\Actions\SendOtpAction;
use App\Modules\Identity\Application\Actions\VerifyPhoneAction;
use App\Modules\Identity\Domain\Models\User;
use App\Modules\Identity\Http\Requests\RegisterCustomerRequest;
use App\Modules\Identity\Http\Resources\CustomerResource;
use App\Modules\Shared\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class CustomerAuthController extends Controller
{
    public function register(RegisterCustomerRequest $request, RegisterCustomerAction $action): JsonResponse
    {
        $user = $action->execute($request->toDTO());

        return ApiResponse::success(new CustomerResource($user), [], 201);
    }

    public function sendOtp(Request $request, SendOtpAction $action): JsonResponse
    {
        $data = $request->validate(['phone_e164' => ['required', 'string', 'regex:/^\+\d{8,15}$/']]);
        $action->execute($data['phone_e164']);

        return ApiResponse::success(['sent' => true], [], 202);
    }

    public function verifyPhone(Request $request, VerifyPhoneAction $action): JsonResponse
    {
        $data = $request->validate([
            'phone_e164' => ['required', 'string', 'regex:/^\+\d{8,15}$/'],
            'code' => ['required', 'string', 'regex:/^\d{6}$/'],
        ]);

        $user = $action->execute($data['phone_e164'], $data['code']);

        return ApiResponse::success(new CustomerResource($user));
    }

    public function login(Request $request, LoginAction $action): JsonResponse
    {
        $data = $request->validate([
            'login' => ['required', 'string'],
            'password' => ['required', 'string'],
            'device_name' => ['sometimes', 'string', 'max:60'],
        ]);

        $result = $action->execute(
            login: $data['login'],
            password: $data['password'],
            ip: $request->ip(),
            useToken: true,
            deviceName: $data['device_name'] ?? null,
        );

        return ApiResponse::success([
            'user' => new CustomerResource($result['user']->load('customerProfile')),
            'token' => $result['token'],
        ]);
    }

    public function logout(Request $request, LogoutAction $action): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $action->execute($user);

        return ApiResponse::success(null, [], 204);
    }
}
