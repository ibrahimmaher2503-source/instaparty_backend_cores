<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\Actions;

use App\Modules\Identity\Application\DTOs\RegisterCustomerDTO;
use App\Modules\Identity\Domain\Events\CustomerRegistered;
use App\Modules\Identity\Domain\Models\CustomerProfile;
use App\Modules\Identity\Domain\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class RegisterCustomerAction
{
    public function __construct(private readonly SendOtpAction $sendOtp) {}

    public function execute(RegisterCustomerDTO $dto): User
    {
        $user = DB::transaction(function () use ($dto): User {
            $user = User::create([
                'name' => $dto->name,
                'phone_e164' => $dto->phoneE164,
                'email' => $dto->email,
                'password' => Hash::make($dto->password),
                'preferred_locale' => $dto->preferredLocale,
                'status' => 'active',
                'timezone' => 'Africa/Cairo',
                'numeral_system' => 'western',
            ]);

            $user->assignRole('customer');

            CustomerProfile::create([
                'user_id' => $user->id,
                'accepts_marketing' => true,
            ]);

            DB::afterCommit(fn () => event(new CustomerRegistered($user)));

            return $user;
        });

        $this->sendOtp->execute($user->phone_e164);
        $user->load(['customerProfile', 'roles']);

        return $user;
    }
}
