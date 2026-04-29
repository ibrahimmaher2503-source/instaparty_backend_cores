<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Domain\Models;

use App\Modules\Catalog\Database\Factories\ServiceSaleDetailFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServiceSaleDetail extends Model
{
    use HasFactory;

    protected static function newFactory(): ServiceSaleDetailFactory
    {
        return ServiceSaleDetailFactory::new();
    }

    protected $primaryKey = 'service_id';

    public $incrementing = false;

    protected $fillable = [
        'service_id',
        'is_perishable',
        'is_made_to_order',
        'lead_time_hours',
        'stock_quantity',
        'customization_fields',
    ];

    protected $casts = [
        'is_perishable'        => 'boolean',
        'is_made_to_order'     => 'boolean',
        'lead_time_hours'      => 'integer',
        'stock_quantity'       => 'integer',
        'customization_fields' => 'array',
    ];

    // -------------------------------------------------------------------------
    // Relationships
    // -------------------------------------------------------------------------

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class, 'service_id');
    }
}
