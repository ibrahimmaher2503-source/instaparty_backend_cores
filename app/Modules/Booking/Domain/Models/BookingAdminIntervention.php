<?php

declare(strict_types=1);

namespace App\Modules\Booking\Domain\Models;

use App\Modules\Booking\Domain\Enums\CustomerConsentStatus;
use App\Modules\Booking\Domain\Enums\InterventionType;
use App\Modules\Identity\Domain\Models\User;
use App\Modules\Identity\Domain\Models\VendorProfile;
use App\Modules\Shared\Domain\Concerns\HasPublicId;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property string $public_id
 * @property int $booking_id
 * @property Booking|null $booking
 * @property int $admin_id
 * @property User|null $admin
 * @property InterventionType $intervention_type
 * @property string $reason
 * @property array<string, mixed> $before_state
 * @property array<string, mixed> $after_state
 * @property CustomerConsentStatus|null $customer_consent_status
 * @property int|null $proposed_vendor_id
 * @property VendorProfile|null $proposedVendor
 * @property Carbon|null $consent_expires_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class BookingAdminIntervention extends Model
{
    use HasFactory;
    use HasPublicId;

    protected $fillable = [
        'public_id',
        'booking_id',
        'admin_id',
        'intervention_type',
        'reason',
        'before_state',
        'after_state',
        'customer_consent_status',
        'proposed_vendor_id',
        'consent_expires_at',
    ];

    protected $casts = [
        'before_state' => 'array',
        'after_state' => 'array',
        'intervention_type' => InterventionType::class,
        'customer_consent_status' => CustomerConsentStatus::class,
        'consent_expires_at' => 'datetime',
    ];

    /** @return BelongsTo<Booking, $this> */
    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    /** @return BelongsTo<User, $this> */
    public function admin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'admin_id');
    }

    /** @return BelongsTo<VendorProfile, $this> */
    public function proposedVendor(): BelongsTo
    {
        return $this->belongsTo(VendorProfile::class, 'proposed_vendor_id');
    }
}
