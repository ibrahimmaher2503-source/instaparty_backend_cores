<?php

declare(strict_types=1);

namespace App\Modules\Shared\Domain\Models;

use Illuminate\Database\Eloquent\Model;

class FeatureFlag extends Model
{
    protected $table = 'feature_flags';

    protected $fillable = [
        'key',
        'is_enabled',
        'rollout_pct',
        'description',
    ];

    protected function casts(): array
    {
        return [
            'is_enabled' => 'boolean',
            'rollout_pct' => 'integer',
        ];
    }
}
