<?php

declare(strict_types=1);

use App\Modules\Communication\Domain\Enums\EventCategory;
use App\Modules\Communication\Domain\Enums\NotificationChannel;
use App\Modules\Communication\Domain\Models\NotificationPreference;
use App\Modules\Identity\Domain\Models\User;
use Illuminate\Support\Str;

function notifPrefMakeCustomer(): User
{
    $user = User::factory()->create();
    $user->assignRole('customer');

    return $user;
}

function notifPrefMakeVendor(): User
{
    $user = User::factory()->create();
    $user->assignRole('vendor');

    return $user;
}

// Auth tests
it('GET customer preferences returns 401 when unauthenticated', function () {
    $this->getJson('/api/v1/customer/notification-preferences')
        ->assertStatus(401);
})->group('communication');

it('PUT customer preference returns 401 when unauthenticated', function () {
    $this->putJson('/api/v1/customer/notification-preferences/push/booking', ['is_enabled' => true])
        ->assertStatus(401);
})->group('communication');

// Authorization tests
it('vendor cannot access customer notification preferences endpoint (403)', function () {
    $vendor = notifPrefMakeVendor();
    $this->actingAs($vendor, 'sanctum')
        ->getJson('/api/v1/customer/notification-preferences')
        ->assertStatus(403);
})->group('communication');

it('customer cannot access vendor notification preferences endpoint (403)', function () {
    $customer = notifPrefMakeCustomer();
    $this->actingAs($customer, 'sanctum')
        ->getJson('/api/v1/vendor/notification-preferences')
        ->assertStatus(403);
})->group('communication');

// Happy path tests
it('GET customer notification preferences returns list', function () {
    $customer = notifPrefMakeCustomer();
    NotificationPreference::create([
        'public_id' => Str::ulid()->toBase32(),
        'user_id' => $customer->id,
        'channel' => NotificationChannel::Push->value,
        'event_category' => EventCategory::Booking->value,
        'is_enabled' => true,
    ]);

    $this->actingAs($customer, 'sanctum')
        ->getJson('/api/v1/customer/notification-preferences')
        ->assertStatus(200)
        ->assertJsonPath('data.0.channel', 'push')
        ->assertJsonPath('data.0.event_category', 'booking')
        ->assertJsonPath('data.0.is_enabled', true);
})->group('communication');

it('PUT customer notification preference updates and returns resource', function () {
    $customer = notifPrefMakeCustomer();

    $this->actingAs($customer, 'sanctum')
        ->putJson('/api/v1/customer/notification-preferences/push/marketing', [
            'is_enabled' => false,
        ])
        ->assertStatus(200)
        ->assertJsonPath('data.channel', 'push')
        ->assertJsonPath('data.event_category', 'marketing')
        ->assertJsonPath('data.is_enabled', false);
})->group('communication');

// Validation tests
it('PUT returns 422 when is_enabled is missing', function () {
    $customer = notifPrefMakeCustomer();

    $this->actingAs($customer, 'sanctum')
        ->putJson('/api/v1/customer/notification-preferences/push/booking', [])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['is_enabled']);
})->group('communication');

it('PUT returns 422 when quiet_hours_start format is invalid', function () {
    $customer = notifPrefMakeCustomer();

    $this->actingAs($customer, 'sanctum')
        ->putJson('/api/v1/customer/notification-preferences/push/booking', [
            'is_enabled' => true,
            'quiet_hours_start' => 'not-a-time',
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['quiet_hours_start']);
})->group('communication');

it('PUT returns 422 when quiet_hours_start provided but quiet_hours_end missing', function () {
    $customer = notifPrefMakeCustomer();

    $this->actingAs($customer, 'sanctum')
        ->putJson('/api/v1/customer/notification-preferences/push/booking', [
            'is_enabled' => true,
            'quiet_hours_start' => '22:00',
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['quiet_hours_end']);
})->group('communication');

it('PUT returns 422 with system_notifications_cannot_be_disabled when disabling system category', function () {
    $customer = notifPrefMakeCustomer();

    $this->actingAs($customer, 'sanctum')
        ->putJson('/api/v1/customer/notification-preferences/push/system', [
            'is_enabled' => false,
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['is_enabled']);
})->group('communication');

// Locale tests
it('GET preferences returns same shape for en and ar (no translatable text)', function () {
    $customer = notifPrefMakeCustomer();
    NotificationPreference::create([
        'public_id' => Str::ulid()->toBase32(),
        'user_id' => $customer->id,
        'channel' => NotificationChannel::Push->value,
        'event_category' => EventCategory::Booking->value,
        'is_enabled' => true,
    ]);

    $enResponse = $this->actingAs($customer, 'sanctum')
        ->getJson('/api/v1/customer/notification-preferences', ['Accept-Language' => 'en'])
        ->assertStatus(200)
        ->json('data');

    $arResponse = $this->actingAs($customer, 'sanctum')
        ->getJson('/api/v1/customer/notification-preferences', ['Accept-Language' => 'ar'])
        ->assertStatus(200)
        ->json('data');

    expect(array_keys($enResponse[0]))->toBe(array_keys($arResponse[0]));
})->group('communication');
