<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\Actions;

use App\Modules\Identity\Domain\Models\User;
use Laravel\Sanctum\PersonalAccessToken;

class LogoutAction
{
    public function execute(User $user): void
    {
        $token = $user->currentAccessToken();

        // Sanctum returns TransientToken (no delete()) for cookie auth.
        // PHPDoc claims PersonalAccessToken only, so we guard at runtime.
        if ($token instanceof PersonalAccessToken) {
            $token->delete();

            return;
        }

        $user->tokens()->delete();
    }
}
