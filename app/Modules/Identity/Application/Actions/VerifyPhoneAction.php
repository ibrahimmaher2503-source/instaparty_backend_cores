<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\Actions;

use App\Modules\Identity\Application\Services\OtpRateLimiter;
use App\Modules\Identity\Domain\Contracts\OtpGatewayInterface;
use App\Modules\Identity\Domain\Events\PhoneVerified;
use App\Modules\Identity\Domain\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class VerifyPhoneAction
{
    public function __construct(
        private readonly OtpGatewayInterface $gateway,
        private readonly OtpRateLimiter $rateLimiter,
    ) {}

    public function execute(string $phoneE164, string $code): User
    {
        $this->rateLimiter->assertNotLockedOut($phoneE164);

        $user = User::where('phone_e164', $phoneE164)->first();

        if ($user === null) {
            throw ValidationException::withMessages(['phone_e164' => __('identity.user_not_found')]);
        }

        if (! $this->gateway->verify($phoneE164, $code)) {
            throw ValidationException::withMessages(['code' => __('identity.invalid_otp')]);
        }

        return DB::transaction(function () use ($user): User {
            $user->phone_verified_at = now();
            $user->save();

            DB::afterCommit(fn () => event(new PhoneVerified($user)));

            return $user;
        });
    }
}
