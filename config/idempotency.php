<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Idempotency Configuration
|--------------------------------------------------------------------------
|
| Per-scope TTLs (in seconds) for the `idempotency_keys` table.
| The `scope` column distinguishes inbound HTTP keys from internal
| Action keys so they never collide.
|
| Scopes:
|   http            — inbound HTTP endpoints (Idempotency-Key header).
|                     24 h window matches the Payment-Card-Industry
|                     standard for webhook replay windows.
|   internal_webhook — monotonic external references such as Paymob
|                     order/transaction IDs. 30-day TTL because gateways
|                     can retry refund callbacks weeks after origination.
|   internal_action  — caller-supplied nonces for internal Action calls
|                     (e.g., commission accrual). 24 h TTL.
|
*/

return [

    'ttl' => [
        'http'             => (int) env('IDEMPOTENCY_TTL_HTTP', 86_400),       // 24 h
        'internal_webhook' => (int) env('IDEMPOTENCY_TTL_WEBHOOK', 2_592_000), // 30 days
        'internal_action'  => (int) env('IDEMPOTENCY_TTL_ACTION', 86_400),     // 24 h
    ],

];
