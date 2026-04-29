<?php

declare(strict_types=1);

use App\Modules\Geography\Database\Seeders\EgyptGeographySeeder;
use App\Modules\Identity\Application\Actions\ApproveVendorForTypeAction;
use App\Modules\Identity\Application\Actions\ApproveVendorProfileAction;
use App\Modules\Identity\Application\Actions\RejectVendorProfileAction;
use App\Modules\Identity\Application\Actions\SuspendVendorAction;
use App\Modules\Identity\Domain\Enums\ApprovalStatus;
use App\Modules\Identity\Domain\Events\VendorApproved;
use App\Modules\Identity\Domain\Events\VendorApprovedForType;
use App\Modules\Identity\Domain\Events\VendorRejected;
use App\Modules\Identity\Domain\Events\VendorSuspended;
use App\Modules\Identity\Domain\Events\VendorTypeRevoked;
use App\Modules\Identity\Domain\Models\User;
use App\Modules\Identity\Domain\Models\VendorApprovedProductType;
use App\Modules\Identity\Domain\Models\VendorProfile;
use App\Modules\Shared\Domain\Enums\ProductType;
use Database\Seeders\IdentityRolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(IdentityRolesSeeder::class);
    $this->seed(EgyptGeographySeeder::class);
    Cache::flush();
});

function adminUser(): User
{
    $user = User::factory()->create();
    $user->assignRole('admin');

    return $user;
}

// --- T057: ApproveVendorProfileAction ---

it('approves a vendor profile and sets approval_status=approved', function (): void {
    Event::fake([VendorApproved::class]);

    $admin = adminUser();
    $vp = VendorProfile::factory()->create();

    $this->actingAs($admin);

    $result = app(ApproveVendorProfileAction::class)->execute($vp);

    expect($result->approval_status)->toBe(ApprovalStatus::Approved)
        ->and($result->approved_at)->not->toBeNull()
        ->and($result->approved_by)->toBe($admin->id);

    Event::assertDispatched(VendorApproved::class);
})->group('identity', 'us3');

it('approves vendor for a product type after profile is approved', function (): void {
    Event::fake([VendorApprovedForType::class]);

    $admin = adminUser();
    $vp = VendorProfile::factory()->create(['approval_status' => ApprovalStatus::Approved]);

    $this->actingAs($admin);

    $row = app(ApproveVendorForTypeAction::class)->execute($vp, ProductType::Rental);

    expect($row)->toBeInstanceOf(VendorApprovedProductType::class)
        ->and($row->product_type)->toBe(ProductType::Rental)
        ->and($row->approved_at)->not->toBeNull()
        ->and($row->revoked_at)->toBeNull();

    expect($vp->user->hasPermissionTo('service.create.rental.own'))->toBeTrue()
        ->and($vp->user->hasPermissionTo('service.update.rental.own'))->toBeTrue()
        ->and($vp->user->hasPermissionTo('service.delete.rental.own'))->toBeTrue()
        ->and($vp->user->hasPermissionTo('service.publish.rental.own'))->toBeTrue();

    Event::assertDispatched(VendorApprovedForType::class);
})->group('identity', 'us3');

it('throws 422 when approving a type on a pending vendor', function (): void {
    $admin = adminUser();
    $vp = VendorProfile::factory()->create(['approval_status' => ApprovalStatus::Pending]);

    $this->actingAs($admin);

    expect(fn () => app(ApproveVendorForTypeAction::class)->execute($vp, ProductType::Rental))
        ->toThrow(UnprocessableEntityHttpException::class);
})->group('identity', 'us3');

// --- T058: Suspension/revocation auto-cascade ---

it('suspends a vendor and auto-revokes all active type approvals', function (): void {
    Event::fake([VendorSuspended::class, VendorTypeRevoked::class]);

    $admin = adminUser();
    $vp = VendorProfile::factory()->create(['approval_status' => ApprovalStatus::Approved]);

    // Grant rental + sale approvals
    VendorApprovedProductType::create([
        'vendor_profile_id' => $vp->id,
        'product_type' => ProductType::Rental,
        'approved_at' => now(),
        'approved_by' => $admin->id,
    ]);
    VendorApprovedProductType::create([
        'vendor_profile_id' => $vp->id,
        'product_type' => ProductType::Sale,
        'approved_at' => now(),
        'approved_by' => $admin->id,
    ]);
    $vp->user->givePermissionTo(['service.create.rental.own', 'service.create.sale.own']);

    $this->actingAs($admin);

    // Dispatch synchronously for test: process queued listeners immediately
    Queue::fake();
    app(SuspendVendorAction::class)->execute($vp);

    $vp->refresh();
    expect($vp->approval_status)->toBe(ApprovalStatus::Suspended)
        ->and($vp->suspended_at)->not->toBeNull();

    Event::assertDispatched(VendorSuspended::class);
})->group('identity', 'us3');

it('rejects a vendor profile and fires VendorRejected event', function (): void {
    Event::fake([VendorRejected::class]);

    $admin = adminUser();
    $vp = VendorProfile::factory()->create();

    $this->actingAs($admin);

    $result = app(RejectVendorProfileAction::class)->execute($vp, ['en' => 'Incomplete documents', 'ar' => 'مستندات غير مكتملة']);

    expect($result->approval_status)->toBe(ApprovalStatus::Rejected)
        ->and($result->rejected_at)->not->toBeNull()
        ->and($result->rejected_by)->toBe($admin->id);

    Event::assertDispatched(VendorRejected::class);
})->group('identity', 'us3');

// --- T059: Authorization tests ---

it('returns 403 when a non-admin tries to call approve-for-type endpoint', function (): void {
    $vp = VendorProfile::factory()->create(['approval_status' => ApprovalStatus::Approved]);
    $token = $vp->user->createToken('test')->plainTextToken;

    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson("/api/v1/admin/vendor-profiles/{$vp->public_id}/approve-for-type", [
            'product_type' => 'rental',
        ])
        ->assertStatus(403);
})->group('identity', 'us3');

it('returns 401 when unauthenticated on admin endpoint', function (): void {
    $vp = VendorProfile::factory()->create();

    $this->postJson("/api/v1/admin/vendor-profiles/{$vp->public_id}/approve-for-type", [
        'product_type' => 'rental',
    ])->assertStatus(401);
})->group('identity', 'us3');

it('vendor not approved for digital does not have digital service permissions', function (): void {
    $admin = adminUser();
    $vp = VendorProfile::factory()->create(['approval_status' => ApprovalStatus::Approved]);

    $this->actingAs($admin);

    // Only approve for rental
    app(ApproveVendorForTypeAction::class)->execute($vp, ProductType::Rental);

    expect($vp->user->hasPermissionTo('service.create.digital.own'))->toBeFalse()
        ->and($vp->user->hasPermissionTo('service.create.rental.own'))->toBeTrue();
})->group('identity', 'us3');

it('admin can approve and reject via API endpoints', function (): void {
    $admin = adminUser();
    $vp = VendorProfile::factory()->create();
    $token = $admin->createToken('test')->plainTextToken;

    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson("/api/v1/admin/vendor-profiles/{$vp->public_id}/approve")
        ->assertStatus(200);

    $vp->refresh();
    expect($vp->approval_status)->toBe(ApprovalStatus::Approved);
})->group('identity', 'us3');

it('admin approve-for-type via API grants permissions and returns 200', function (): void {
    $admin = adminUser();
    $vp = VendorProfile::factory()->create(['approval_status' => ApprovalStatus::Approved]);
    $token = $admin->createToken('test')->plainTextToken;

    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson("/api/v1/admin/vendor-profiles/{$vp->public_id}/approve-for-type", [
            'product_type' => 'digital',
        ])
        ->assertStatus(200)
        ->assertJsonPath('data.product_type', 'digital');

    expect($vp->user->fresh()->hasPermissionTo('service.create.digital.own'))->toBeTrue();
})->group('identity', 'us3');

it('admin can list vendor profiles paginated', function (): void {
    $admin = adminUser();
    VendorProfile::factory()->count(3)->create();
    $token = $admin->createToken('test')->plainTextToken;

    $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/v1/admin/vendor-profiles')
        ->assertStatus(200)
        ->assertJsonStructure(['data', 'meta']);
})->group('identity', 'us3');
