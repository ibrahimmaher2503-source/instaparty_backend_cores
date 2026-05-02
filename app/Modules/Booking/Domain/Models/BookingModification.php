<?php

declare(strict_types=1);

namespace App\Modules\Booking\Domain\Models;

use App\Modules\Booking\Domain\Enums\ModificationProposalKind;
use App\Modules\Booking\Domain\Enums\ModificationStatus;
use App\Modules\Shared\Domain\Concerns\HasPublicId;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $public_id
 * @property int $booking_vendor_id
 * @property int $proposed_by
 * @property ModificationProposalKind $proposal_kind
 * @property ModificationStatus $status
 * @property Carbon|null $customer_decision_at
 * @property Carbon|null $expires_at
 * @property array<string,string>|null $vendor_explanation
 * @property array<string,mixed> $diff_snapshot
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class BookingModification extends Model
{
    use HasPublicId;

    protected $fillable = [
        'public_id',
        'booking_vendor_id',
        'proposed_by',
        'proposal_kind',
        'status',
        'customer_decision_at',
        'expires_at',
        'vendor_explanation',
        'diff_snapshot',
    ];

    /** @return array<string,mixed> */
    protected function casts(): array
    {
        return [
            'proposal_kind' => ModificationProposalKind::class,
            'status' => ModificationStatus::class,
            'vendor_explanation' => 'array',
            'diff_snapshot' => 'array',
            'customer_decision_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<BookingVendor, $this> */
    public function bookingVendor(): BelongsTo
    {
        return $this->belongsTo(BookingVendor::class);
    }

    /** @return HasMany<BookingModificationItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(BookingModificationItem::class, 'booking_modification_id');
    }

    /** @return BelongsTo<\App\Modules\Identity\Domain\Models\User, $this> */
    public function proposedBy(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Identity\Domain\Models\User::class, 'proposed_by');
    }
}
