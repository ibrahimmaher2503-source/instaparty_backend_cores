<?php

declare(strict_types=1);

namespace App\Modules\Shared\Domain\Concerns;

use Illuminate\Support\Str;

/**
 * @property string $public_id
 */
trait HasPublicId
{
    public static function bootHasPublicId(): void
    {
        static::creating(function ($model): void {
            if (empty($model->public_id)) {
                $model->public_id = (string) Str::ulid();
            }
        });
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }
}
