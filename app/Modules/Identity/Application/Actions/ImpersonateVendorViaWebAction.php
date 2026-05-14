<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\Actions;

use App\Modules\Identity\Domain\Models\User;
use App\Modules\Identity\Domain\Models\VendorProfile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;

class ImpersonateVendorViaWebAction
{
    public function execute(VendorProfile $vendorProfile, User $adminUser): void
    {
        DB::transaction(function () use ($vendorProfile, $adminUser): void {
            DB::table('audit_logs')->insert([
                'public_id'       => (string) Str::ulid(),
                'auditable_type'  => VendorProfile::class,
                'auditable_id'    => $vendorProfile->id,
                'user_id'         => $adminUser->id,
                'action'          => 'vendor_web_impersonated',
                'changes'         => json_encode(['method' => 'filament_web_session']),
                'ip_address'      => Request::ip(),
                'user_agent'      => Request::userAgent(),
                'created_at'      => now(),
            ]);
        });

        Session::put('impersonator_id', $adminUser->id);
        Session::put('impersonator_name', $adminUser->name);
        Session::put('impersonation_started_at', now()->toIso8601String());
        Session::regenerate();

        Auth::loginUsingId($vendorProfile->user_id);
    }
}
