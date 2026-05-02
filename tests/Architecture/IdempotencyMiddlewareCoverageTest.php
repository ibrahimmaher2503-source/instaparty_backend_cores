<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

it('payment mutating routes have idempotency middleware', function (): void {
    $routes = collect(Route::getRoutes())->keyBy(fn ($route) => $route->uri());
    expect(implode(',', $routes['api/v1/customer/bookings/{bookingPublicId}/payments']->middleware()))->toContain('idempotency');
    expect(implode(',', $routes['api/v1/admin/bookings/{bookingPublicId}/refunds']->middleware()))->toContain('idempotency');
});
