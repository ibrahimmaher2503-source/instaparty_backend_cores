<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\Actions;

use App\Modules\Identity\Domain\Models\User;
use Illuminate\Support\Facades\DB;

class UpdateVendorEmailAction
{
    public function execute(User $user, string $newEmail): void
    {
        DB::transaction(function () use ($user, $newEmail): void {
            $user->forceFill([
                'email' => $newEmail,
                'email_verified_at' => null,
            ])->save();
        });
    }
}
