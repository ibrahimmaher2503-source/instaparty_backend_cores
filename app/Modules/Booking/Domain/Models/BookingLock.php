<?php

declare(strict_types=1);

namespace App\Modules\Booking\Domain\Models;

use App\Modules\Booking\Database\Factories\BookingLockFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BookingLock extends Model
{
    use HasFactory;

    public const UPDATED_AT = null;

    protected $fillable = [
        'resource_type', 'resource_id', 'lock_token',
        'locked_by_user_id', 'lock_purpose',
        'acquired_at', 'expires_at', 'released_at',
    ];

    protected $casts = [
        'acquired_at' => 'datetime',
        'expires_at' => 'datetime',
        'released_at' => 'datetime',
    ];

    protected static function newFactory(): BookingLockFactory
    {
        return BookingLockFactory::new();
    }
}
