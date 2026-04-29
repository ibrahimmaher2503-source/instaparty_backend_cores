<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\Actions;

use App\Modules\Identity\Application\DTOs\UpdateCustomerProfileDTO;
use App\Modules\Identity\Domain\Models\CustomerProfile;
use App\Modules\Identity\Domain\Models\User;
use Illuminate\Support\Facades\DB;

class UpdateCustomerProfileAction
{
    public function execute(User $user, UpdateCustomerProfileDTO $dto): User
    {
        return DB::transaction(function () use ($user, $dto): User {
            $userAttrs = $dto->userAttributes();
            if ($userAttrs !== []) {
                $user->fill($userAttrs)->save();
            }

            $profileAttrs = $dto->profileAttributes();
            if ($profileAttrs !== []) {
                CustomerProfile::query()->updateOrCreate(
                    ['user_id' => $user->id],
                    $profileAttrs,
                );
            }

            $user->load(['customerProfile']);

            return $user;
        });
    }
}
