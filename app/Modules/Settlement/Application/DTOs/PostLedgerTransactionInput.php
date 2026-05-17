<?php

declare(strict_types=1);

namespace App\Modules\Settlement\Application\DTOs;

use App\Modules\Settlement\Domain\Enums\TransactionKind;

final readonly class PostLedgerTransactionInput
{
    /**
     * @param  list<LedgerEntryInput>  $entries
     */
    public function __construct(
        public TransactionKind $kind,
        public string $currency,
        public string $idempotencyKey,
        public string $correlationId,
        public ?string $causationId,
        public string $initiatorType,
        public ?int $initiatedByUserId,
        public array $entries,
        public ?string $descriptionKey = null,
        public ?array $descriptionParams = null,
        public ?array $metadata = null,
    ) {}
}
