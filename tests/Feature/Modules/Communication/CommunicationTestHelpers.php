<?php

declare(strict_types=1);

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

if (! function_exists('createBookingForUser')) {
    function createBookingForUser(int $userId, string $productType, Carbon $createdAt): void
    {
        $bookingId = DB::table('bookings')->insertGetId([
            'public_id' => Str::ulid()->toBase32(),
            'user_id' => $userId,
            'lifecycle_status' => 'confirmed',
            'payment_status' => 'captured',
            'fulfillment_status' => 'pending',
            'currency' => 'EGP',
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ]);

        DB::table('booking_items')->insert([
            'public_id' => Str::ulid()->toBase32(),
            'booking_id' => $bookingId,
            'product_type' => $productType,
            'item_status' => 'confirmed',
            'unit_price_minor' => 10000,
            'unit_price_currency' => 'EGP',
            'quantity' => 1,
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ]);
    }
}
