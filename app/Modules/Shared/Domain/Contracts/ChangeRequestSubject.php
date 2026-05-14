<?php

declare(strict_types=1);

namespace App\Modules\Shared\Domain\Contracts;

use App\Modules\Shared\Domain\Enums\ChangeRequestSubjectType;

interface ChangeRequestSubject
{
    public function getChangeRequestSubjectType(): ChangeRequestSubjectType;

    public function getKey(): int;
}
