<?php

declare(strict_types=1);

namespace App\Modules\Advertising\Domain\Models;

use App\Modules\Advertising\Domain\Enums\AdSubscriptionStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VendorAdSubscription extends Model
{
    protected $table = 'vendor_ad_subscriptions';

    protected $fillable = [
        'public_id',
        'vendor_profile_id',
        'advertisement_package_id',
        'status',
        'starts_at',
        'ends_at',
        'total_minor',
        'total_currency',
        'impression_count',
        'click_count',
    ];

    protected function casts(): array
    {
        return [
            'status'    => AdSubscriptionStatus::class,
            'starts_at' => 'datetime',
            'ends_at'   => 'datetime',
        ];
    }

    public function package(): BelongsTo
    {
        return $this->belongsTo(AdvertisementPackage::class, 'advertisement_package_id');
    }

    public function vendor(): BelongsTo
    {
        // String reference avoids a direct cross-module model import
        return $this->belongsTo('App\Modules\Identity\Domain\Models\VendorProfile', 'vendor_profile_id');
    }
}
