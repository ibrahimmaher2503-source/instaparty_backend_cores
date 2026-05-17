<?php

declare(strict_types=1);

use App\Modules\Payments\Domain\Enums\PaymentMethod;
use App\Modules\Payments\Domain\Enums\PaymentStatus;
use App\Modules\Payments\Domain\Models\Payment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

it('concurrent duplicate webhooks produce exactly one capture group', function (): void {
    if (! extension_loaded('pcntl')) {
        $this->markTestSkipped('pcntl required for concurrent fork test');
    }

    config()->set('services.paymob.hmac_secret', 'concurrent-test-secret');

    $data    = makeConfirmedBookingWithItem();
    $payment = Payment::create([
        'public_id'       => (string) Str::ulid(),
        'booking_id'      => $data['booking']->id,
        'user_id'         => $data['customer']->id,
        'gateway'         => 'paymob',
        'gateway_ref'     => 'PMB-CONC-' . Str::ulid(),
        'amount_minor'    => 80_000,
        'amount_currency' => 'EGP',
        'method'          => PaymentMethod::Card,
        'status'          => PaymentStatus::Pending,
    ]);

    $payload = [
        'type' => 'TRANSACTION',
        'obj'  => [
            'id'           => $payment->gateway_ref,
            'order'        => ['id' => 'CONC-ORDER-' . $payment->id],
            'success'      => true,
            'amount_cents' => 80_000,
        ],
    ];
    $signature = hash_hmac('sha512', json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '', 'concurrent-test-secret');

    // Fork 5 workers each posting the same webhook
    $pids = [];
    for ($i = 0; $i < 5; $i++) {
        $pid = pcntl_fork();
        if ($pid === 0) {
            // Child process: re-boot the app and post the webhook
            $app = require base_path('bootstrap/app.php');
            $app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

            $kernel   = $app->make(\Illuminate\Contracts\Http\Kernel::class);
            $request  = \Illuminate\Http\Request::create('/api/webhooks/paymob', 'POST', [], [], [], [], json_encode($payload));
            $request->headers->set('HMAC', $signature);
            $request->headers->set('Content-Type', 'application/json');

            $kernel->handle($request);

            exit(0);
        }
        $pids[] = $pid;
    }

    // Wait for all children
    foreach ($pids as $pid) {
        pcntl_waitpid($pid, $status);
    }

    // Exactly one capture group created across all concurrent calls
    $groupCount = DB::table('ledger_transaction_groups')
        ->where('kind', 'payment_capture')
        ->count();

    expect($groupCount)->toBe(1);
})->group('concurrency', 'us2');
