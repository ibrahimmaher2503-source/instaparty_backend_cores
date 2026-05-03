<?php

declare(strict_types=1);

namespace App\Modules\Reviews\Domain\Models;

use App\Modules\Reviews\Domain\Enums\ModerationStatus;
use App\Modules\Reviews\Domain\Enums\ReviewLocale;
use App\Modules\Reviews\Domain\Enums\ReviewType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReviewResponse extends Model
{
    protected $table = 'review_responses';

    protected $fillable = [
        'review_type',
        'review_id',
        'vendor_profile_id',
        'body',
        'locale',
        'moderation_status',
    ];

    protected function casts(): array
    {
        return [
            'review_type'       => ReviewType::class,
            'locale'            => ReviewLocale::class,
            'moderation_status' => ModerationStatus::class,
        ];
    }

    public function vendorProfile(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Identity\Domain\Models\VendorProfile::class, 'vendor_profile_id');
    }
}
