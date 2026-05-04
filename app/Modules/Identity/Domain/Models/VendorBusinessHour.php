<?php

declare(strict_types=1);

namespace App\Modules\Identity\Domain\Models;

use App\Modules\Identity\Domain\Enums\DayOfWeek;
use Database\Factories\VendorBusinessHourFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VendorBusinessHour extends Model
{
    /** @use HasFactory<VendorBusinessHourFactory> */
    use HasFactory;

    protected $fillable = [
        'vendor_profile_id',
        'day_of_week',
        'opens_at',
        'closes_at',
    ];

    public function vendorProfile(): BelongsTo
    {
        return $this->belongsTo(VendorProfile::class);
    }

    protected static function newFactory(): VendorBusinessHourFactory
    {
        return VendorBusinessHourFactory::new();
    }

    protected function casts(): array
    {
        return [
            'day_of_week' => DayOfWeek::class,
        ];
    }
}
