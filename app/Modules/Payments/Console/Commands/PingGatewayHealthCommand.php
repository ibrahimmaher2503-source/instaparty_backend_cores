<?php

declare(strict_types=1);

namespace App\Modules\Payments\Console\Commands;

use App\Modules\Payments\Domain\Contracts\PaymentGateway;
use App\Modules\Payments\Domain\Models\GatewayHealthPing;
use Illuminate\Console\Command;

class PingGatewayHealthCommand extends Command
{
    protected $signature = 'payments:ping-gateway-health';

    protected $description = 'Ping configured payment gateways and record latency + success in gateway_health_pings.';

    public function __construct(
        private readonly PaymentGateway $gateway,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $result = $this->gateway->ping();

        GatewayHealthPing::query()->create([
            'gateway_code' => $result->gatewayCode,
            'latency_ms' => $result->latencyMs,
            'success' => $result->success,
            'error_message' => $result->errorMessage,
            'checked_at' => now(),
        ]);

        $this->line(sprintf(
            '[%s] %s — %dms%s',
            $result->gatewayCode,
            $result->success ? 'OK' : 'FAIL',
            $result->latencyMs,
            $result->errorMessage ? ' ('.$result->errorMessage.')' : '',
        ));

        return self::SUCCESS;
    }
}
