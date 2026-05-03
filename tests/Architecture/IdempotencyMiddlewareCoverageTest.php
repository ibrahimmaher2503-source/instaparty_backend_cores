<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

it('payment mutating routes have idempotency middleware', function (): void {
    $routes = collect(Route::getRoutes())->keyBy(fn ($route) => $route->uri());
    expect(implode(',', $routes['api/v1/customer/bookings/{bookingPublicId}/payments']->middleware()))->toContain('idempotency');
    expect(implode(',', $routes['api/v1/admin/bookings/{bookingPublicId}/refunds']->middleware()))->toContain('idempotency');
});

// ─────────────────────────────────────────────────────
// T704 — Settlement: withdrawal POST route has idempotency middleware
// ─────────────────────────────────────────────────────

it('POST api/v1/vendor/withdrawals has idempotency middleware', function (): void {
    $routes = collect(Route::getRoutes())->keyBy(fn ($route) => $route->uri());
    expect($routes->has('api/v1/vendor/withdrawals'))->toBeTrue('Withdrawal route not registered');
    $withdrawalRoute = $routes['api/v1/vendor/withdrawals'];
    expect(implode(',', $withdrawalRoute->middleware()))->toContain('idempotency');
});
