<?php

declare(strict_types=1);

namespace App\Modules\Geography\Domain\Models;

use App\Modules\Geography\Database\Factories\RegionFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Translatable\HasTranslations;

class Region extends Model
{
    /** @use HasFactory<RegionFactory> */
    use HasFactory, HasTranslations, HasUlids;

    protected $fillable = [
        'public_id',
        'governorate_id',
        'name',
        'is_active',
        'sort_order',
    ];

    public array $translatable = ['name'];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function uniqueIds(): array
    {
        return ['public_id'];
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    protected static function newFactory(): RegionFactory
    {
        return RegionFactory::new();
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeForGovernorate(Builder $query, int $governorateId): Builder
    {
        return $query->where('governorate_id', $governorateId);
    }

    public function governorate(): BelongsTo
    {
        return $this->belongsTo(Governorate::class);
    }

    public function cities(): HasMany
    {
        return $this->hasMany(City::class);
    }
}
