<?php

declare(strict_types=1);

namespace App\Modules\Advertising\Domain\Models;

use App\Modules\Advertising\Domain\Enums\PlacementType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Translatable\HasTranslations;

class AdvertisementPackage extends Model
{
    use HasTranslations;

    protected $table = 'advertisement_packages';

    protected $fillable = [
        'public_id',
        'name',
        'description',
        'placement_type',
        'duration_days',
        'price_minor',
        'price_currency',
        'impression_limit',
        'is_active',
    ];

    /** @var list<string> */
    public array $translatable = ['name', 'description'];

    protected function casts(): array
    {
        return [
            'placement_type'  => PlacementType::class,
            'is_active'       => 'boolean',
        ];
    }

    public function vendorSubscriptions(): HasMany
    {
        return $this->hasMany(VendorAdSubscription::class, 'advertisement_package_id');
    }
}
