<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\Services;

use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;

class OtpRateLimiter
{
    private const int MAX_SEND_ATTEMPTS = 3;

    private const int WINDOW_SECONDS = 600;

    private const int LOCKOUT_SECONDS = 3600;

    public function assertNotLockedOut(string $phoneE164): void
    {
        if (Cache::has($this->lockoutKey($phoneE164))) {
            throw new TooManyRequestsHttpException(
                self::LOCKOUT_SECONDS,
                'OTP lockout active for this phone number.'
            );
        }
    }

    public function recordSendAttempt(string $phoneE164): void
    {
        $this->assertNotLockedOut($phoneE164);

        $attemptsKey = $this->attemptsKey($phoneE164);
        $attempts = (int) Cache::get($attemptsKey, 0) + 1;

        Cache::put($attemptsKey, $attempts, now()->addSeconds(self::WINDOW_SECONDS));

        if ($attempts > self::MAX_SEND_ATTEMPTS) {
            Cache::put($this->lockoutKey($phoneE164), '1', now()->addSeconds(self::LOCKOUT_SECONDS));
            Cache::forget($attemptsKey);

            throw new TooManyRequestsHttpException(
                self::LOCKOUT_SECONDS,
                'Too many OTP send attempts. Please try again later.'
            );
        }
    }

    public function clear(string $phoneE164): void
    {
        Cache::forget($this->attemptsKey($phoneE164));
        Cache::forget($this->lockoutKey($phoneE164));
    }

    private function attemptsKey(string $phoneE164): string
    {
        return "otp_attempts:{$phoneE164}";
    }

    private function lockoutKey(string $phoneE164): string
    {
        return "otp_lockout:{$phoneE164}";
    }
}
