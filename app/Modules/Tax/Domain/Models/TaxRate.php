<?php

declare(strict_types=1);

namespace App\Modules\Tax\Domain\Models;

use App\Modules\Tax\Domain\Enums\TaxAppliesTo;
use Illuminate\Database\Eloquent\Model;
use Spatie\Translatable\HasTranslations;

class TaxRate extends Model
{
    use HasTranslations;

    protected $table = 'tax_rates';

    protected $fillable = [
        'public_id',
        'name',
        'description',
        'rate_bps',
        'applies_to',
        'product_types',
        'is_tax_inclusive',
        'is_active',
        'effective_from',
        'effective_to',
    ];

    /** @var list<string> */
    public array $translatable = ['name', 'description'];

    protected function casts(): array
    {
        return [
            'applies_to'       => TaxAppliesTo::class,
            'product_types'    => 'array',
            'is_tax_inclusive'  => 'boolean',
            'is_active'        => 'boolean',
            'effective_from'   => 'date',
            'effective_to'     => 'date',
        ];
    }

    public function scopeActive(\Illuminate\Database\Eloquent\Builder $query): \Illuminate\Database\Eloquent\Builder
    {
        return $query->where('is_active', true)
            ->where('effective_from', '<=', now())
            ->where(fn ($q) => $q->whereNull('effective_to')->orWhere('effective_to', '>', now()));
    }
}
