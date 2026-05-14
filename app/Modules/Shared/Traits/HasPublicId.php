<?php

declare(strict_types=1);

namespace App\Modules\Shared\Traits;

use Illuminate\Support\Str;

trait HasPublicId
{
    protected static function booted(): void
    {
        static::creating(function (self $model): void {
            if (! $model->public_id) {
                $model->public_id = Str::ulid();
            }
        });
    }
}
