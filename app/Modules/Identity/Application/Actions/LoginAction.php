<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\Actions;

use App\Modules\Identity\Domain\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class LoginAction
{
    /**
     * @return array{user: User, token: ?string}
     */
    public function execute(string $login, string $password, ?string $ip = null, bool $useToken = true, ?string $deviceName = null): array
    {
        $field = filter_var($login, FILTER_VALIDATE_EMAIL) ? 'email' : 'phone_e164';

        $user = User::where($field, $login)->first();

        if ($user === null || ! Hash::check($password, $user->password)) {
            throw ValidationException::withMessages([
                'login' => __('identity.invalid_credentials'),
            ]);
        }

        $user->forceFill([
            'last_login_at' => now(),
            'last_login_ip' => $ip,
        ])->save();

        $token = $useToken ? $user->createToken($deviceName ?? 'api')->plainTextToken : null;

        return ['user' => $user, 'token' => $token];
    }
}
