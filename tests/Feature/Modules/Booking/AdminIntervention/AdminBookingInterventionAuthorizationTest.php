<?php

declare(strict_types=1);

use Database\Factories\UserFactory;
use Database\Seeders\IdentityRolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $this->seed(IdentityRolesSeeder::class);
});

it('redirects unauthenticated users from the intervention page', function (): void {
    $response = $this->get('/admin/admin-booking-intervention');
    $response->assertRedirect();
})->group('booking', 'intervention');

it('returns 403 when admin lacks booking.intervene.access permission', function (): void {
    $admin = UserFactory::new()->create();
    $this->actingAs($admin);

    $response = $this->get('/admin/admin-booking-intervention');
    $response->assertForbidden();
})->group('booking', 'intervention');

it('renders the page for admin with booking.intervene.access', function (): void {
    $admin = UserFactory::new()->asAdmin()->create();
    $admin->givePermissionTo('booking.intervene.access');
    $this->actingAs($admin);

    $response = $this->get('/admin/admin-booking-intervention');
    $response->assertOk();
})->group('booking', 'intervention');
