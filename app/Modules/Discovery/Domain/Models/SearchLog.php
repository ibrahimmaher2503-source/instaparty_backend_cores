<?php

declare(strict_types=1);

namespace App\Modules\Discovery\Domain\Models;

use Illuminate\Database\Eloquent\Model;

class SearchLog extends Model
{
    /** @var string|null Append-only: no updated_at */
    public const UPDATED_AT = null;

    protected $fillable = [
        'user_id',
        'query',
        'locale',
        'filters',
        'results_count',
        'clicked_service_id',
    ];

    protected $casts = [
        'filters'       => 'array',
        'results_count' => 'integer',
    ];
}
