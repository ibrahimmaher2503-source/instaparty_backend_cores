<?php

declare(strict_types=1);

namespace App\Modules\Settlement\Application\DTOs;

final readonly class SettlementRunResult
{
    /**
     * @param  list<string>  $errors
     */
    public function __construct(
        public int $settled,
        public int $failed,
        public array $errors,
    ) {}
}
