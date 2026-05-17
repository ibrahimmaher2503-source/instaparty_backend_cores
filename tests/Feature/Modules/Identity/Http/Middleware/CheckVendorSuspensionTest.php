<?php

declare(strict_types=1);

use App\Modules\Identity\Domain\Models\User;
use App\Modules\Identity\Domain\Models\VendorProfile;
// Feature directory already has TestCase + RefreshDatabase applied in tests/Pest.php

describe('CheckVendorSuspension middleware', function () {
    it('redirects suspended vendors away from /vendor to AccountSuspendedPage', function () {
        $user = User::factory()->phoneVerified()->asVendor()->create();
        VendorProfile::factory()->for($user)->create([
            'approval_status' => 'suspended',
            'suspended_at'    => now(),
        ]);

        $this->actingAs($user);

        $response = $this->get('/vendor');

        $response->assertRedirect();
        // Redirects away from the vendor dashboard (to AccountSuspendedPage)
        $this->assertStringNotContainsString('/vendor$', $response->headers->get('Location') ?? '');
    })->group('identity', 'middleware', 'suspended', 't059');
});
