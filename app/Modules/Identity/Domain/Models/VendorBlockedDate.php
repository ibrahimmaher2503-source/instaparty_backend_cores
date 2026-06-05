<?php

declare(strict_types=1);

namespace App\Modules\Identity\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Translatable\HasTranslations;

class VendorBlockedDate extends Model
{
    use HasTranslations;

    /** @var array<int, string> */
    public array $translatable = ['reason'];

    protected $fillable = [
        'public_id',
        'vendor_profile_id',
        'blocked_date',
        'reason',
    ];

    protected $casts = [
        'blocked_date' => 'date:Y-m-d',
        'reason' => 'array',
    ];

    public function vendorProfile(): BelongsTo
    {
        return $this->belongsTo(VendorProfile::class);
    }
}
