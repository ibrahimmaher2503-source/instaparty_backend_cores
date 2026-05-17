<?php

declare(strict_types=1);

namespace App\Modules\Settlement\Application\DTOs;

use App\Modules\Settlement\Domain\Enums\LedgerDirection;
use App\Modules\Settlement\Domain\Enums\LedgerEntryType;

final readonly class LedgerEntryInput
{
    public function __construct(
        public string $walletOwnerType,
        public int $walletOwnerId,
        public LedgerDirection $direction,
        public int $amountMinor,
        public LedgerEntryType $entryType,
        public ?string $counterAccountType = null,
        public ?int $counterAccountId = null,
        public ?string $descriptionKey = null,
        public ?array $descriptionParams = null,
        public ?string $relatedEntityType = null,
        public ?int $relatedEntityId = null,
    ) {}
}
