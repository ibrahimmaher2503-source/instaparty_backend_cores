<?php

declare(strict_types=1);

use App\Modules\Booking\Filament\Vendor\Pages\VendorBookingDecisionPage;
use App\Modules\Booking\Filament\Vendor\Pages\VendorBookingModificationBuilder;
use App\Modules\Identity\Domain\Models\VendorProfile;
use Database\Seeders\IdentityRolesSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\PermissionRegistrar;

require_once __DIR__.'/../../BookingTestHelpers.php';

uses(RefreshDatabase::class);

beforeEach(function (): void {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $this->seed(IdentityRolesSeeder::class);
    Filament::setCurrentPanel(Filament::getPanel('vendor'));
    $this->data = makeSubmittedBookingWithVendor();
});

it('mounts the builder page and creates a draft modification', function (): void {
    Livewire::actingAs($this->data['vendor']->user)
        ->test(VendorBookingModificationBuilder::class, [
            'bookingVendor' => $this->data['bookingVendor']->public_id,
        ])
        ->assertOk();

    expect($this->data['bookingVendor']->modifications()->where('status', 'draft')->count())->toBe(1);
})->group('booking', 'modification-builder');

it('returns 403 when a different vendor opens the builder', function (): void {
    $otherVendor = VendorProfile::factory()->approved()->create();

    Livewire::actingAs($otherVendor->user)
        ->test(VendorBookingModificationBuilder::class, [
            'bookingVendor' => $this->data['bookingVendor']->public_id,
        ])
        ->assertForbidden();
})->group('booking', 'modification-builder', 'auth');

it('decision page modify action redirects to the builder', function (): void {
    Livewire::actingAs($this->data['vendor']->user)
        ->test(VendorBookingDecisionPage::class, [
            'bookingVendor' => $this->data['bookingVendor']->public_id,
        ])
        ->callAction('modify')
        ->assertRedirect(VendorBookingModificationBuilder::getUrl([
            'bookingVendor' => $this->data['bookingVendor']->public_id,
        ]));
})->group('booking', 'modification-builder');
