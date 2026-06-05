<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Review finding (idempotency): commit e968cb7 changed SubmitBookingAction's
 * requestHash() to include the request body. Rows stored before that change
 * were hashed WITHOUT the body, so a post-deploy replay of an in-flight key
 * would fail hash_equals and 409 a legitimate retry.
 *
 * These keys carry a 24h TTL and are only an idempotency cache (the booking
 * submit is guarded at the domain level by lifecycle state), so purging the
 * route's rows on deploy is safe and closes the format-mismatch window.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('idempotency_keys')->where('route', 'bookings.submit')->delete();
    }

    public function down(): void
    {
        // No-op: idempotency cache rows are not restorable and self-expire.
    }
};
