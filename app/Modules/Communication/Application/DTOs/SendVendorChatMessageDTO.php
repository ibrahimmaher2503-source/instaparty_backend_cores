<?php

declare(strict_types=1);

namespace App\Modules\Communication\Application\DTOs;

final readonly class SendVendorChatMessageDTO
{
    public function __construct(
        public string $body,
    ) {}
}
