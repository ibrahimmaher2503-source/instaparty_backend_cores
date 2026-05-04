<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\Actions;

use App\Modules\Identity\Domain\Models\User;
use App\Modules\Identity\Domain\Models\VendorProfile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\Str;

class ImpersonateVendorAction
{
    private string $token = '';

    public function execute(VendorProfile $vendorProfile, User $adminUser): string
    {
        DB::transaction(function () use ($vendorProfile, $adminUser): void {
            activity()
                ->on($vendorProfile)
                ->causedBy($adminUser)
                ->withProperties([
                    'token_ability' => 'impersonation',
                    'expires_minutes' => 30,
                    'ip_address' => Request::ip(),
                ])
                ->log('vendor_impersonated');

            DB::table('audit_logs')->insert([
                'public_id' => (string) Str::ulid(),
                'auditable_type' => VendorProfile::class,
                'auditable_id' => $vendorProfile->id,
                'user_id' => $adminUser->id,
                'action' => 'vendor_impersonated',
                'changes' => json_encode([
                    'token_ability' => 'impersonation',
                    'expires_minutes' => 30,
                ]),
                'ip_address' => Request::ip(),
                'user_agent' => Request::userAgent(),
                'created_at' => now(),
            ]);

            DB::afterCommit(function () use ($vendorProfile, $adminUser): void {
                $this->token = $vendorProfile->user
                    ->createToken(
                        'admin-impersonation-'.$adminUser->id.'-'.now()->timestamp,
                        ['impersonation'],
                        now()->addMinutes(30),
                    )
                    ->plainTextToken;
            });
        });

        return $this->token;
    }
}
