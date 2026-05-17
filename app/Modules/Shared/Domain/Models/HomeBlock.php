<?php

declare(strict_types=1);

namespace App\Modules\Shared\Domain\Models;

use App\Modules\Shared\Domain\Concerns\HasPublicId;
use App\Modules\Shared\Domain\Enums\HomeBlockType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class HomeBlock extends Model
{
    use HasPublicId;

    protected $table = 'home_blocks';

    protected $fillable = [
        'public_id', 'block_type', 'name', 'position',
        'is_visible', 'payload', 'starts_at', 'ends_at', 'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'block_type' => HomeBlockType::class,
            'is_visible' => 'boolean',
            'payload' => 'array',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
        ];
    }

    public function scopeVisible(Builder $query): Builder
    {
        return $query->where('is_visible', true);
    }

    public function scopeInWindow(Builder $query): Builder
    {
        $now = now();

        return $query
            ->where(fn (Builder $q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', $now))
            ->where(fn (Builder $q) => $q->whereNull('ends_at')->orWhere('ends_at', '>=', $now));
    }
}
