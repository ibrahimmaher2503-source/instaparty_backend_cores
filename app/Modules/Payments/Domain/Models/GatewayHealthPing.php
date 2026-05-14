<?php

declare(strict_types=1);

namespace App\Modules\Payments\Domain\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class GatewayHealthPing extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'gateway_code', 'latency_ms', 'success', 'error_message', 'checked_at',
    ];

    protected $casts = [
        'success' => 'boolean',
        'checked_at' => 'datetime',
    ];

    public function scopeForGateway(Builder $query, string $gatewayCode): Builder
    {
        return $query->where('gateway_code', $gatewayCode);
    }

    public function scopeLastHours(Builder $query, int $hours): Builder
    {
        return $query->where('checked_at', '>=', now()->subHours($hours));
    }
}
