<?php

declare(strict_types=1);

namespace App\Modules\Discovery\Domain\Models;

use App\Modules\Catalog\Domain\Models\Service;
use App\Modules\Discovery\Database\Factories\SearchLogFactory;
use App\Modules\Identity\Domain\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SearchLog extends Model
{
    use HasFactory;

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
        'filters' => 'array',
        'results_count' => 'integer',
    ];

    protected static function newFactory(): SearchLogFactory
    {
        return SearchLogFactory::new();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function clickedService(): BelongsTo
    {
        return $this->belongsTo(Service::class, 'clicked_service_id');
    }
}
