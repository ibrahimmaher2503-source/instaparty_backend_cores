<?php

declare(strict_types=1);

namespace App\Modules\Booking\Domain\Models;

use App\Modules\Booking\Database\Factories\BookingAddressFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string|null $address_line
 * @property string|null $building
 * @property string|null $floor
 * @property string|null $apartment
 * @property string|null $landmark
 * @property string|null $recipient_name
 * @property string|null $recipient_phone_e164
 * @property int $city_id
 */
class BookingAddress extends Model
{
    use HasFactory;

    protected $fillable = [
        'booking_id', 'city_id',
        'address_line', 'building', 'floor', 'apartment', 'landmark',
        'latitude', 'longitude',
        'recipient_name', 'recipient_phone_e164',
    ];

    protected $casts = [
        'latitude' => 'decimal:7',
        'longitude' => 'decimal:7',
    ];

    protected static function newFactory(): BookingAddressFactory
    {
        return BookingAddressFactory::new();
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }
}
