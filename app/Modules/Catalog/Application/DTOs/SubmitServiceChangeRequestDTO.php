<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Application\DTOs;

final readonly class SubmitServiceChangeRequestDTO
{
    /**
     * @param array<string, mixed> $proposedPayload Full validated request payload from the vendor
     * @param array{en: string, ar: string}|null $vendorNote Optional bilingual vendor explanation
     */
    public function __construct(
        public int $serviceId,
        public int $vendorUserId,
        public int $vendorProfileId,
        public array $proposedPayload,
        public ?array $vendorNote,
        public ?string $idempotencyKey,
    ) {}
}
