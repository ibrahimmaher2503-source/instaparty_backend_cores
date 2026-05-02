<?php

declare(strict_types=1);

namespace App\Modules\Payments\Domain\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class IdempotencyKey extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'key', 'user_id', 'route', 'request_hash', 'response_status', 'response_body', 'expires_at', 'created_at',
    ];

    protected $casts = [
        'response_body' => 'array',
        'expires_at' => 'datetime',
        'created_at' => 'datetime',
    ];

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('expires_at', '>', now());
    }

    public function scopeExpired(Builder $query): Builder
    {
        return $query->where('expires_at', '<=', now());
    }
}
