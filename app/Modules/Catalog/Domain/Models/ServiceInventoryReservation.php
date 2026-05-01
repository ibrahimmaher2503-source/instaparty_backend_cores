<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Domain\Models;

use App\Modules\Catalog\Database\Factories\ServiceInventoryReservationFactory;
use App\Modules\Catalog\Domain\Enums\HoldType;
use App\Modules\Catalog\Domain\Enums\ProductType;
use App\Modules\Catalog\Domain\Enums\ReservationStatus;
use App\Modules\Shared\Domain\Concerns\HasPublicId;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServiceInventoryReservation extends Model
{
    use HasFactory;
    use HasPublicId;

    protected static function newFactory(): ServiceInventoryReservationFactory
    {
        return ServiceInventoryReservationFactory::new();
    }

    protected $fillable = [
        'public_id',
        'service_id',
        'user_id',
        'product_type',
        'hold_type',
        'status',
        'reserved_starts_at',
        'reserved_ends_at',
        'quantity',
        'expires_at',
        'booking_item_id',
    ];

    protected $casts = [
        'product_type'        => ProductType::class,
        'hold_type'           => HoldType::class,
        'status'              => ReservationStatus::class,
        'reserved_starts_at'  => 'datetime',
        'reserved_ends_at'    => 'datetime',
        'expires_at'          => 'datetime',
        'quantity'            => 'integer',
    ];

    // -------------------------------------------------------------------------
    // Relationships
    // -------------------------------------------------------------------------

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    // -------------------------------------------------------------------------
    // Scopes
    // -------------------------------------------------------------------------

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereIn('status', [
            ReservationStatus::Held->value,
            ReservationStatus::Confirmed->value,
        ]);
    }

    public function scopeExpired(Builder $query): Builder
    {
        return $query->where('status', ReservationStatus::Held)
            ->where('expires_at', '<', now());
    }

    public function scopeForService(Builder $query, int $serviceId): Builder
    {
        return $query->where('service_id', $serviceId);
    }

    public function scopeOverlapping(Builder $query, Carbon $start, Carbon $end): Builder
    {
        return $query->whereNotNull('reserved_starts_at')
            ->where('reserved_starts_at', '<', $end)
            ->where('reserved_ends_at', '>', $start);
    }
}
