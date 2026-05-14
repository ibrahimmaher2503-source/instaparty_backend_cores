<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\Actions;

use App\Modules\Identity\Domain\Models\User;
use Illuminate\Support\Facades\DB;

// Phase 1.0 stub — OTP verification added in Phase 5.0.
class UpdateVendorPhoneAction
{
    public function execute(User $user, string $phoneE164): void
    {
        DB::transaction(function () use ($user, $phoneE164): void {
            $user->forceFill([
                'phone_e164' => $phoneE164,
                'phone_verified_at' => null,
            ])->save();
        });
    }
}
