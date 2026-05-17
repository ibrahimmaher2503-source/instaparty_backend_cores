<?php

declare(strict_types=1);

use App\Modules\Settlement\Domain\Contracts\WalletLocker;
use App\Modules\Settlement\Infrastructure\Locks\RedisWalletLocker;
use Illuminate\Support\Facades\Cache;

it('acquires a lock and releases it successfully', function (): void {
    $locker = app(WalletLocker::class);

    $handle = $locker->tryAcquire(walletId: 999, ttlSeconds: 5, waitSeconds: 2);

    expect($handle)->not()->toBeNull();
    expect($handle->isStillHeld())->toBeTrue();

    $handle->release();

    expect($handle->isStillHeld())->toBeFalse();
})->group('us3');

it('returns null when a second lock attempt times out on the same wallet', function (): void {
    $locker = app(WalletLocker::class);

    $first = $locker->tryAcquire(walletId: 998, ttlSeconds: 5, waitSeconds: 0);
    expect($first)->not()->toBeNull();

    // Second attempt with zero wait should fail immediately (lock already held)
    $second = $locker->tryAcquire(walletId: 998, ttlSeconds: 5, waitSeconds: 0);
    expect($second)->toBeNull();

    $first?->release();
})->group('us3');

it('tryAcquireMany returns null if any wallet lock times out and releases acquired locks', function (): void {
    $locker = app(WalletLocker::class);

    // Pre-acquire lock on wallet 102 to simulate contention
    $blocker = $locker->tryAcquire(walletId: 102, ttlSeconds: 5, waitSeconds: 0);
    expect($blocker)->not()->toBeNull();

    // Try to acquire [101, 102] — 102 is held so this should fail
    $handles = $locker->tryAcquireMany([101, 102], ttlSeconds: 5, waitSeconds: 0);
    expect($handles)->toBeNull();

    // Wallet 101 lock should have been released (not leaked)
    $check = $locker->tryAcquire(walletId: 101, ttlSeconds: 5, waitSeconds: 0);
    expect($check)->not()->toBeNull();
    $check?->release();

    $blocker?->release();
})->group('us3');

it('renew extends the lock TTL', function (): void {
    $locker = app(WalletLocker::class);

    $handle = $locker->tryAcquire(walletId: 997, ttlSeconds: 2, waitSeconds: 1);
    expect($handle)->not()->toBeNull();

    $renewed = $handle?->renew(5);
    expect($renewed)->toBeTrue();

    $handle?->release();
})->group('us3');
