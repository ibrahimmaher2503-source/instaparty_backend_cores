<?php

declare(strict_types=1);

namespace App\Modules\Booking\Domain\Models;

use App\Modules\Booking\Domain\Enums\ModificationChangeKind;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $booking_modification_id
 * @property int|null $target_booking_item_id
 * @property ModificationChangeKind $change_kind
 * @property array<string,mixed> $payload
 * @property \Carbon\Carbon $created_at
 */
class BookingModificationItem extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'booking_modification_id',
        'target_booking_item_id',
        'change_kind',
        'payload',
    ];

    /** @return array<string,mixed> */
    protected function casts(): array
    {
        return [
            'change_kind' => ModificationChangeKind::class,
            'payload'     => 'array',
            'created_at'  => 'datetime',
        ];
    }

    public function modification(): BelongsTo
    {
        return $this->belongsTo(BookingModification::class, 'booking_modification_id');
    }

    public function targetItem(): BelongsTo
    {
        return $this->belongsTo(BookingItem::class, 'target_booking_item_id');
    }
}
