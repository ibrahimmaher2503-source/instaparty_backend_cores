<?php

declare(strict_types=1);

namespace App\Modules\Identity\Domain\Contracts;

interface OtpGatewayInterface
{
    public function send(string $phoneE164, string $code): void;

    public function verify(string $phoneE164, string $code): bool;
}
