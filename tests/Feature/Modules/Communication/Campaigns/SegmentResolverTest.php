<?php

declare(strict_types=1);

use App\Modules\Communication\Application\Services\InvalidSegmentFilterException;
use App\Modules\Communication\Application\Services\SegmentResolver;
use App\Modules\Identity\Domain\Models\User;

beforeEach(function () {
    $this->resolver = app(SegmentResolver::class);
});

it('resolves rental bookings within 30 days', function () {
    $matchUser = User::factory()->asCustomer()->create(['preferred_locale' => 'en', 'status' => 'active']);
    $tooOldUser = User::factory()->asCustomer()->create(['preferred_locale' => 'en', 'status' => 'active']);
    $saleUser = User::factory()->asCustomer()->create(['preferred_locale' => 'en', 'status' => 'active']);

    createBookingForUser($matchUser->id, 'rental', now()->subDays(5));
    createBookingForUser($tooOldUser->id, 'rental', now()->subDays(60));
    createBookingForUser($saleUser->id, 'sale', now()->subDays(5));

    $result = $this->resolver->resolve(['booked_product_type' => 'rental', 'booked_within_days' => 30]);

    expect($result)->toContain($matchUser->id)
        ->not->toContain($tooOldUser->id)
        ->not->toContain($saleUser->id);
})->group('communication', 'rental');

it('resolves sale bookings within 30 days', function () {
    $matchUser = User::factory()->asCustomer()->create(['preferred_locale' => 'en', 'status' => 'active']);
    createBookingForUser($matchUser->id, 'sale', now()->subDays(10));

    $result = $this->resolver->resolve(['booked_product_type' => 'sale', 'booked_within_days' => 30]);

    expect($result)->toContain($matchUser->id);
})->group('communication', 'sale');

it('resolves digital bookings within 30 days', function () {
    $matchUser = User::factory()->asCustomer()->create(['preferred_locale' => 'en', 'status' => 'active']);
    createBookingForUser($matchUser->id, 'digital', now()->subDays(3));

    $result = $this->resolver->resolve(['booked_product_type' => 'digital', 'booked_within_days' => 30]);

    expect($result)->toContain($matchUser->id);
})->group('communication', 'digital');

it('resolves across all product types when no type filter given', function () {
    $rentalUser = User::factory()->asCustomer()->create(['preferred_locale' => 'en', 'status' => 'active']);
    $saleUser = User::factory()->asCustomer()->create(['preferred_locale' => 'en', 'status' => 'active']);

    createBookingForUser($rentalUser->id, 'rental', now()->subDays(5));
    createBookingForUser($saleUser->id, 'sale', now()->subDays(5));

    $result = $this->resolver->resolve(['booked_within_days' => 30]);

    expect($result)->toContain($rentalUser->id)->toContain($saleUser->id);
})->group('communication', 'rental', 'sale', 'digital');

it('deduplicates customers with multiple qualifying bookings', function () {
    $user = User::factory()->asCustomer()->create(['preferred_locale' => 'en', 'status' => 'active']);
    createBookingForUser($user->id, 'rental', now()->subDays(5));
    createBookingForUser($user->id, 'rental', now()->subDays(10));

    $result = $this->resolver->resolve(['booked_product_type' => 'rental', 'booked_within_days' => 30]);

    expect($result->filter(fn ($id) => $id === $user->id)->count())->toBe(1);
})->group('communication');

it('excludes suspended users', function () {
    $suspended = User::factory()->asCustomer()->create(['status' => 'suspended']);
    createBookingForUser($suspended->id, 'rental', now()->subDays(5));

    $result = $this->resolver->resolve(['booked_product_type' => 'rental', 'booked_within_days' => 30]);

    expect($result)->not->toContain($suspended->id);
})->group('communication');

it('throws on empty segment filters', function () {
    $this->resolver->resolve([]);
})->throws(InvalidSegmentFilterException::class)->group('communication');

it('throws on unknown filter key', function () {
    $this->resolver->resolve(['unknown_key' => 'value']);
})->throws(InvalidSegmentFilterException::class)->group('communication');
