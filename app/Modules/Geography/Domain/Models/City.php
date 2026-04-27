<?php

declare(strict_types=1);

namespace App\Modules\Geography\Domain\Models;

use App\Modules\Geography\Database\Factories\CityFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Translatable\HasTranslations;

class City extends Model
{
    /** @use HasFactory<CityFactory> */
    use HasFactory, HasTranslations, HasUlids;

    protected $fillable = [
        'public_id',
        'region_id',
        'governorate_id',
        'name',
        'latitude',
        'longitude',
        'is_active',
        'sort_order',
    ];

    public array $translatable = ['name'];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'sort_order' => 'integer',
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
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

    protected static function newFactory(): CityFactory
    {
        return CityFactory::new();
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeForGovernorate(Builder $query, int $governorateId): Builder
    {
        return $query->where('governorate_id', $governorateId);
    }

    public function scopeForRegion(Builder $query, int $regionId): Builder
    {
        return $query->where('region_id', $regionId);
    }

    public function region(): BelongsTo
    {
        return $this->belongsTo(Region::class);
    }

    public function governorate(): BelongsTo
    {
        return $this->belongsTo(Governorate::class);
    }
}
