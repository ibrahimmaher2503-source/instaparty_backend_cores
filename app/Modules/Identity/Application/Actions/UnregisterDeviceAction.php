<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\Actions;

use App\Modules\Identity\Domain\Models\UserDevice;
use Illuminate\Support\Facades\DB;

class UnregisterDeviceAction
{
    /**
     * Idempotent: deleting an unknown token is a no-op. Only the
     * authenticated user's own rows are ever touched.
     */
    public function execute(int $userId, string $fcmToken): void
    {
        DB::transaction(fn () => UserDevice::query()
            ->where('user_id', $userId)
            ->where('fcm_token', $fcmToken)
            ->delete());
    }
}
