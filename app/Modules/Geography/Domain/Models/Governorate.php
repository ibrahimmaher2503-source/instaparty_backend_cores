<?php

declare(strict_types=1);

namespace App\Modules\Geography\Domain\Models;

use App\Modules\Geography\Database\Factories\GovernorateFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Translatable\HasTranslations;

class Governorate extends Model
{
    /** @use HasFactory<GovernorateFactory> */
    use HasFactory, HasTranslations, HasUlids;

    protected $fillable = [
        'public_id',
        'country_id',
        'name',
        'code',
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

    protected static function newFactory(): GovernorateFactory
    {
        return GovernorateFactory::new();
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    public function regions(): HasMany
    {
        return $this->hasMany(Region::class);
    }

    public function cities(): HasMany
    {
        return $this->hasMany(City::class);
    }
}
