<?php

declare(strict_types=1);

namespace App\Modules\Payments\Domain\Models;

use App\Modules\Payments\Domain\Enums\RefundReasonCode;
use App\Modules\Payments\Domain\Enums\RefundStatus;
use App\Modules\Shared\Domain\Casts\MoneyCast;
use App\Modules\Shared\Domain\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Model;

class Refund extends Model
{
    use HasPublicId;

    protected $fillable = [
        'public_id', 'payment_id', 'booking_id', 'amount_minor', 'amount_currency', 'reason_code',
        'reason_notes', 'gateway_ref', 'status', 'initiated_by', 'processed_at',
    ];

    public array $translatable = ['reason_notes'];

    protected $casts = [
        'amount' => MoneyCast::class . ':amount',
        'reason_code' => RefundReasonCode::class,
        'status' => RefundStatus::class,
        'reason_notes' => 'array',
        'processed_at' => 'datetime',
    ];
}
