<?php

declare(strict_types=1);

namespace App\Modules\Identity\Domain\Models;

use App\Modules\Identity\Domain\Enums\ComplianceEventType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VendorComplianceEvent extends Model
{
    public const CREATED_AT = 'occurred_at';

    public const UPDATED_AT = null;

    protected $table = 'vendor_compliance_events';

    protected $fillable = [
        'public_id',
        'vendor_profile_id',
        'document_id',
        'event_type',
        'occurred_at',
        'admin_id',
        'reason',
    ];

    protected $casts = [
        'occurred_at' => 'datetime',
        'event_type' => ComplianceEventType::class,
        'reason' => 'json',
    ];

    protected $translatable = ['reason'];

    public function vendorProfile(): BelongsTo
    {
        return $this->belongsTo(VendorProfile::class, 'vendor_profile_id');
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(VendorDocument::class, 'document_id');
    }
}
