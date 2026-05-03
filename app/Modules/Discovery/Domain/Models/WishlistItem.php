<?php

declare(strict_types=1);

namespace App\Modules\Discovery\Domain\Models;

use App\Modules\Catalog\Domain\Models\Service;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WishlistItem extends Model
{
    use HasFactory;

    /** @var string|null Append-only: no updated_at */
    public const UPDATED_AT = null;

    protected $fillable = [
        'wishlist_id',
        'service_id',
    ];

    public function wishlist(): BelongsTo
    {
        return $this->belongsTo(Wishlist::class);
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }
}
