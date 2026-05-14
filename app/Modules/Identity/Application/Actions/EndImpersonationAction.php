<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\Actions;

use App\Modules\Identity\Domain\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;

class EndImpersonationAction
{
    public function execute(): ?User
    {
        $adminId = Session::get('impersonator_id');

        if ($adminId === null) {
            return null;
        }

        Session::forget(['impersonator_id', 'impersonation_started_at', 'impersonator_name']);
        Session::regenerate();

        $admin = User::find($adminId);

        if ($admin !== null) {
            Auth::loginUsingId($adminId);
        }

        return $admin;
    }
}
