<?php

declare(strict_types=1);

namespace App\Modules\Communication\Application\Services;

readonly class ResolvedTemplate
{
    public function __construct(
        public int $templateId,
        public string $body,
        public ?string $subject,
        /** @var array<string, mixed> */
        public array $variables,
    ) {}
}
