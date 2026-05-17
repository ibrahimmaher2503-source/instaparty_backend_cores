<?php

declare(strict_types=1);

namespace App\Modules\Settlement\Application\DTOs;

final readonly class RunReconciliationInput
{
    /**
     * @param  array<string, mixed>  $scopeParams  e.g. ['wallet_id' => 42] or ['date_from' => '...', 'date_to' => '...']
     */
    public function __construct(
        public string $scopeType,
        public array $scopeParams,
        public string $triggerKind,
        public ?int $triggeredByUserId,
        public ?string $idempotencyKey,
        public string $correlationId,
    ) {}
}
