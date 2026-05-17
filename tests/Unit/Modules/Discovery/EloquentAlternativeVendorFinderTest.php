<?php

declare(strict_types=1);

use App\Modules\Booking\Domain\Models\Booking;
use App\Modules\Discovery\Domain\Contracts\AlternativeVendorFinder;
use App\Modules\Discovery\Infrastructure\Repositories\EloquentAlternativeVendorFinder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('AlternativeVendorFinder is bound in the container', function (): void {
    expect(app(AlternativeVendorFinder::class))->toBeInstanceOf(EloquentAlternativeVendorFinder::class);
})->group('discovery');

it('validateCandidates throws InvalidArgumentException for IDs not in candidate set', function (): void {
    $finder = app(AlternativeVendorFinder::class);
    $booking = Booking::factory()->submitted()->create();

    expect(fn () => $finder->validateCandidates($booking, [999999]))
        ->toThrow(\InvalidArgumentException::class);
})->group('discovery');

it('findCandidates returns a collection', function (): void {
    $finder = app(AlternativeVendorFinder::class);
    $booking = Booking::factory()->submitted()->create();

    $result = $finder->findCandidates($booking);

    expect($result)->toBeInstanceOf(\Illuminate\Support\Collection::class);
})->group('discovery');
