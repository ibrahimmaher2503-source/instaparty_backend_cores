<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Controllers;

use App\Modules\Identity\Application\Actions\ConfirmPasswordResetAction;
use App\Modules\Identity\Application\Actions\LoginAction;
use App\Modules\Identity\Application\Actions\LogoutAction;
use App\Modules\Identity\Application\Actions\RegisterCustomerAction;
use App\Modules\Identity\Application\Actions\RequestPasswordResetAction;
use App\Modules\Identity\Application\Actions\SendOtpAction;
use App\Modules\Identity\Application\Actions\VerifyPhoneAction;
use App\Modules\Identity\Domain\Models\User;
use App\Modules\Identity\Http\Requests\ConfirmPasswordResetRequest;
use App\Modules\Identity\Http\Requests\RegisterCustomerRequest;
use App\Modules\Identity\Http\Requests\RequestPasswordResetRequest;
use App\Modules\Identity\Http\Resources\CustomerResource;
use App\Modules\Shared\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use RuntimeException;

/**
 * @group Auth
 */
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
        $token = $user->createToken('customer-app')->plainTextToken;

        return ApiResponse::success([
            'user' => new CustomerResource($user),
            'token' => $token,
        ]);
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

    /**
     * Feature 054 (B1). Neutral response — never enumerates whether the
     * identifier is registered (FR-EXT-202).
     *
     * @response 202 {"data":{"message":"If an account exists, we sent a code."},"meta":{},"errors":null}
     */
    public function requestPasswordReset(
        RequestPasswordResetRequest $request,
        RequestPasswordResetAction $action,
    ): JsonResponse {
        $action->execute($request->toDTO());

        return ApiResponse::success(
            ['message' => __('auth.password_reset.neutral_success')],
            [],
            202,
        );
    }

    /**
     * Feature 054 (B1, US1 expansion T028c). Verifies the reset token and
     * commits the new password.
     *
     * @response 200 {"data":{"message":"Password updated."},"meta":{},"errors":null}
     * @response 422 {"data":null,"meta":{},"errors":{"message":"Invalid or expired token."}}
     */
    public function confirmPasswordReset(
        ConfirmPasswordResetRequest $request,
        ConfirmPasswordResetAction $action,
    ): JsonResponse {
        try {
            $action->execute($request->toDTO());
        } catch (RuntimeException $e) {
            return ApiResponse::error(
                ['message' => __('auth.password_reset.invalid_or_expired')],
                422,
            );
        }

        return ApiResponse::success(
            ['message' => __('auth.password_reset.updated')],
            [],
            200,
        );
    }
}
