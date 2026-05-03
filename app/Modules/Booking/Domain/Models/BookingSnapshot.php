<?php

declare(strict_types=1);

namespace App\Modules\Booking\Domain\Models;

use App\Modules\Shared\Domain\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BookingSnapshot extends Model
{
    use HasPublicId;

    public const UPDATED_AT = null;

    protected $fillable = [
        'public_id', 'booking_id', 'version', 'snapshot',
        'trigger_kind', 'trigger_reference_type', 'trigger_reference_id', 'triggered_by',
    ];

    protected $casts = [
        'snapshot' => 'array',
        'version' => 'integer',
    ];

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }
}
