<?php

declare(strict_types=1);

use App\Modules\Catalog\Domain\Enums\ProductType;
use App\Modules\Catalog\Domain\Models\Category;
use App\Modules\Catalog\Domain\Models\CategoryFieldSchema;
use App\Modules\Catalog\Domain\Models\Service;
use App\Modules\Communication\Domain\Enums\NotificationChannel;
use App\Modules\Communication\Domain\Models\NotificationDispatch;
use App\Modules\Identity\Domain\Models\User;
use App\Modules\Identity\Domain\Models\VendorProfile;
use App\Modules\Settlement\Domain\Models\CommissionRate;
use Database\Factories\VendorApprovedProductTypeFactory;
use Database\Seeders\IdentityRolesSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Vendor-portal P3c — notifications inbox (17.1–17.5/G11), commission
 * rates + calculator (10.3/10.4), field schemas (10.2), service stats (4.16).
 */
beforeEach(function (): void {
    $this->seed(IdentityRolesSeeder::class);

    $this->user = User::factory()->phoneVerified()->asVendor()->create();
    $this->vendor = VendorProfile::factory()->approved()->create(['user_id' => $this->user->id]);
    VendorApprovedProductTypeFactory::new()->forType(ProductType::Rental)->create([
        'vendor_profile_id' => $this->vendor->id,
    ]);
});

it('vendor inbox lists own in-app notifications and tracks unread count', function (): void {
    NotificationDispatch::factory()->create([
        'user_id' => $this->user->id,
        'channel' => NotificationChannel::InApp,
        'context' => ['subject' => 'New booking', 'body' => 'You received a booking.'],
    ]);
    NotificationDispatch::factory()->create([ // someone else's
        'channel' => NotificationChannel::InApp,
    ]);

    $this->actingAs($this->user)
        ->getJson('/api/v1/vendor/notifications')
        ->assertStatus(200)
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.title', 'New booking');

    $this->actingAs($this->user)
        ->getJson('/api/v1/vendor/notifications/unread-count')
        ->assertStatus(200)
        ->assertJsonPath('data.unread_count', 1);
})->group('communication', 'vendor-portal', 'notifications');

it('vendor marks one and all notifications read', function (): void {
    $n = NotificationDispatch::factory()->create([
        'user_id' => $this->user->id,
        'channel' => NotificationChannel::InApp,
        'context' => ['subject' => 'S', 'body' => 'B'],
    ]);
    NotificationDispatch::factory()->create([
        'user_id' => $this->user->id,
        'channel' => NotificationChannel::InApp,
        'context' => ['subject' => 'S2', 'body' => 'B2'],
    ]);

    $this->actingAs($this->user)
        ->patchJson("/api/v1/vendor/notifications/{$n->public_id}/mark-read")
        ->assertStatus(200)
        ->assertJsonPath('data.is_read', true);

    $this->actingAs($this->user)
        ->postJson('/api/v1/vendor/notifications/mark-all-read')
        ->assertStatus(200);

    expect(NotificationDispatch::query()
        ->where('user_id', $this->user->id)
        ->whereNull('read_at')
        ->count())->toBe(0);
})->group('communication', 'vendor-portal', 'notifications');

it('customer token gets 403 on the vendor inbox', function (): void {
    $customer = User::factory()->asCustomer()->create();

    $this->actingAs($customer)
        ->getJson('/api/v1/vendor/notifications')
        ->assertStatus(403);
})->group('communication', 'vendor-portal', 'authorization');

it('returns own resolved commission rates per approved type', function (): void {
    CommissionRate::query()->create([
        'public_id' => (string) Str::ulid(),
        'category_id' => null,
        'product_type' => 'rental',
        'commission_bps' => 1500,
        'effective_from' => now()->subDay()->toDateString(),
    ]);

    $response = $this->actingAs($this->user)
        ->getJson('/api/v1/vendor/pricing/commission-rates')
        ->assertStatus(200);

    $global = collect($response->json('data'))
        ->firstWhere(fn (array $r) => $r['category_public_id'] === null && $r['product_type'] === 'rental');

    expect($global)->not->toBeNull()
        ->and($global['commission_bps'])->toBeInt();
})->group('settlement', 'vendor-portal');

it('pricing calculator returns integer minor split', function (): void {
    CommissionRate::query()->create([
        'public_id' => (string) Str::ulid(),
        'category_id' => null,
        'product_type' => 'rental',
        'commission_bps' => 1000,
        'effective_from' => now()->subDay()->toDateString(),
    ]);

    $response = $this->actingAs($this->user)
        ->getJson('/api/v1/vendor/pricing/calculator?amount_minor=50000&product_type=rental')
        ->assertStatus(200);

    expect($response->json('data.commission_minor'))->toBeInt()
        ->and($response->json('data.vendor_payout_minor'))
        ->toBe(50000 - $response->json('data.commission_minor'))
        ->and($response->json('data.currency'))->toBe('EGP');
})->group('settlement', 'vendor-portal', 'money');

it('vendor field-schemas include non-filterable form fields', function (): void {
    $category = Category::factory()->create(['is_active' => true]);
    CategoryFieldSchema::factory()->create([
        'category_id' => $category->id,
        'product_type' => ProductType::Rental,
        'field_key' => 'internal_setup_notes',
        'is_filterable' => false,
        'is_required' => true,
    ]);

    $response = $this->actingAs($this->user)
        ->getJson("/api/v1/vendor/categories/{$category->public_id}/field-schemas?type=rental")
        ->assertStatus(200);

    $row = collect($response->json('data'))->firstWhere('field_key', 'internal_setup_notes');

    expect($row)->not->toBeNull()
        ->and($row['is_required'])->toBeTrue()
        ->and($row['is_filterable'])->toBeFalse();
})->group('catalog', 'vendor-portal', 'rental');

it('service stats count views and stay own-scoped', function (): void {
    $service = Service::factory()->rental()->published()->create(['vendor_profile_id' => $this->vendor->id]);

    DB::table('analytics_events')->insert([
        'event_type' => 'service_view',
        'payload' => json_encode(['service_id' => $service->id, 'product_type' => 'rental', 'user_id' => null]),
        'created_at' => now(),
    ]);

    $this->actingAs($this->user)
        ->getJson("/api/v1/vendor/services/{$service->public_id}/stats")
        ->assertStatus(200)
        ->assertJsonPath('data.views', 1)
        ->assertJsonPath('data.booked_items', 0);

    $foreign = Service::factory()->rental()->published()->create();
    $this->actingAs($this->user)
        ->getJson("/api/v1/vendor/services/{$foreign->public_id}/stats")
        ->assertStatus(404);
})->group('catalog', 'vendor-portal', 'isolation');
