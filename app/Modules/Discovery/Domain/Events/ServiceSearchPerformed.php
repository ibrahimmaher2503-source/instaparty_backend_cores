<?php

declare(strict_types=1);

namespace App\Modules\Discovery\Domain\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ServiceSearchPerformed
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly ?string $query,
        public readonly string $locale,
        public readonly array $filtersApplied,
        public readonly int $resultsCount,
        public readonly ?int $userId,
    ) {}
}
