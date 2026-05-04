<?php

declare(strict_types=1);

namespace App\Modules\Payments\Domain\Models;

use App\Modules\Payments\Database\Factories\GatewayWebhookLogFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GatewayWebhookLog extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'gateway', 'event_type', 'signature_valid', 'payload', 'processed_at', 'processing_error', 'created_at',
    ];

    protected $casts = [
        'payload' => 'array',
        'signature_valid' => 'boolean',
        'processed_at' => 'datetime',
        'created_at' => 'datetime',
    ];

    protected static function newFactory(): GatewayWebhookLogFactory
    {
        return GatewayWebhookLogFactory::new();
    }
}
