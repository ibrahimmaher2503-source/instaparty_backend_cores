<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Domain\Models;

use App\Modules\Catalog\Database\Factories\ServiceDigitalDetailFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServiceDigitalDetail extends Model
{
    use HasFactory;

    protected static function newFactory(): ServiceDigitalDetailFactory
    {
        return ServiceDigitalDetailFactory::new();
    }

    protected $primaryKey = 'service_id';

    public $incrementing = false;

    protected $fillable = [
        'service_id',
        'delivery_method',
        'has_expiry',
        'expiry_days_after_purchase',
        'is_refundable_after_delivery',
        'redemption_url_template',
        'code_pool_id',
    ];

    protected $casts = [
        'has_expiry'                   => 'boolean',
        'is_refundable_after_delivery' => 'boolean',
        'expiry_days_after_purchase'   => 'integer',
        // delivery_method left as string — DeliveryMethod enum to be added in a later layer
    ];

    // -------------------------------------------------------------------------
    // Relationships
    // -------------------------------------------------------------------------

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class, 'service_id');
    }
}
