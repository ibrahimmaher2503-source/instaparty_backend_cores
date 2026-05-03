<?php

declare(strict_types=1);

use App\Modules\Identity\Domain\Models\VendorProfile;
use App\Modules\Settlement\Database\Seeders\SettlementPermissionsSeeder;
use Database\Seeders\IdentityRolesSeeder;
use Spatie\Permission\PermissionRegistrar;

if (! function_exists('seedSettlementRoles')) {
    function seedSettlementRoles(): void
    {
        static $seeded = false;
        if ($seeded) {
            return;
        }
        app(IdentityRolesSeeder::class)->run();
        app(SettlementPermissionsSeeder::class)->run();
        $seeded = false; // Always re-seed after RefreshDatabase wipes tables
    }
}

if (! function_exists('resetSettlementPermissionCache')) {
    function resetSettlementPermissionCache(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}

if (! function_exists('makeVendorWithSettlementPermissions')) {
    /**
     * Create a VendorProfile with the vendor role (includes all settlement.* own permissions).
     * Returns the VendorProfile; access the associated User via $vp->user.
     */
    function makeVendorWithSettlementPermissions(): VendorProfile
    {
        $vp = VendorProfile::factory()->create();
        $vp->user->assignRole('vendor');

        return $vp;
    }
}

if (! function_exists('vendorBearerToken')) {
    /**
     * Create a Sanctum plain-text token for the given VendorProfile's User.
     */
    function vendorBearerToken(VendorProfile $vp): string
    {
        return $vp->user->createToken('test')->plainTextToken;
    }
}

if (! function_exists('validBankAccountPayload')) {
    /**
     * Return a valid bank account array suitable for withdrawal requests.
     */
    function validBankAccountPayload(): array
    {
        return [
            'account_holder' => 'Test Vendor',
            'iban' => 'EG380019000500000000263180002',
            'bank_name' => 'CIB',
            'swift_bic' => 'CIBEEGCX',
        ];
    }
}
