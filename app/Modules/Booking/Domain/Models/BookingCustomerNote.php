<?php

declare(strict_types=1);

namespace App\Modules\Booking\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BookingCustomerNote extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'booking_id', 'user_id', 'body', 'detected_locale',
    ];

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }
}
