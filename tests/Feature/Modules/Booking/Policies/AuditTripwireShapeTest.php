<?php

declare(strict_types=1);

use App\Modules\Booking\Domain\Models\Booking;
use App\Modules\Identity\Domain\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

it('audit row contains all required changes JSON keys for an authenticated user', function (): void {
    $user = User::factory()->create();
    $user->assignRole('super_admin');
    $booking = Booking::factory()->create();

    Gate::forUser($user)->allows('assignReplacementVendor', $booking);

    $row = DB::table('audit_logs')
        ->where('action', 'booking.replacement_vendor_assignment_blocked')
        ->where('auditable_id', $booking->id)
        ->latest('id')
        ->first();

    expect($row)->not->toBeNull('audit row must be written');
    expect($row->user_id)->toBe($user->id);
    expect($row->auditable_type)->toBe(Booking::class);
    expect($row->auditable_id)->toBe($booking->id);

    $changes = json_decode($row->changes, true);
    expect($changes)->toBeArray();
    expect($changes)->toHaveKeys(['attempted_at', 'role', 'source', 'ip', 'user_agent']);
    expect($changes['attempted_at'])->toBeString()->not->toBeEmpty();
    expect($changes['role'])->toBeArray();
    expect($changes['source'])->toBeString();
})->group('booking', 'policy', 'replacement-vendor-guard');

it('audit row has null user_id and empty role array for guest', function (): void {
    $booking = Booking::factory()->create();

    Gate::forUser(null)->allows('assignReplacementVendor', $booking);

    $row = DB::table('audit_logs')
        ->where('action', 'booking.replacement_vendor_assignment_blocked')
        ->where('auditable_id', $booking->id)
        ->latest('id')
        ->first();

    expect($row)->not->toBeNull('guest audit row must be written');
    expect($row->user_id)->toBeNull();

    $changes = json_decode($row->changes, true);
    expect($changes)->toHaveKeys(['attempted_at', 'role', 'source', 'ip', 'user_agent']);
    expect($changes['role'])->toBe([]);
})->group('booking', 'policy', 'replacement-vendor-guard');
