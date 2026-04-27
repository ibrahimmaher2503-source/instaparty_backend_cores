<?php

declare(strict_types=1);

namespace App\Modules\Geography\Domain\Models;

use App\Modules\Geography\Database\Factories\CountryFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Translatable\HasTranslations;

class Country extends Model
{
    /** @use HasFactory<CountryFactory> */
    use HasFactory, HasTranslations, HasUlids;

    protected $fillable = [
        'public_id',
        'name',
        'iso2',
        'iso3',
        'default_currency',
        'default_locale',
        'default_timezone',
        'phone_code',
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

    protected static function newFactory(): CountryFactory
    {
        return CountryFactory::new();
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function governorates(): HasMany
    {
        return $this->hasMany(Governorate::class);
    }
}
