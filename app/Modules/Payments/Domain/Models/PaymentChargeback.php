<?php

declare(strict_types=1);

namespace App\Modules\Payments\Domain\Models;

use App\Models\User;
use App\Modules\Payments\Domain\Enums\ChargebackStatus;
use App\Modules\Shared\Domain\Casts\MoneyCast;
use App\Modules\Shared\Domain\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Translatable\HasTranslations;

class PaymentChargeback extends Model
{
    use HasPublicId;
    use HasTranslations;

    protected $fillable = [
        'public_id', 'payment_id', 'gateway_case_id', 'reason', 'status',
        'amount_minor', 'amount_currency', 'opened_at', 'resolved_at',
        'admin_notes', 'created_by', 'updated_by',
    ];

    public array $translatable = ['reason', 'admin_notes'];

    protected $casts = [
        'status' => ChargebackStatus::class,
        'amount' => MoneyCast::class.':amount',
        'opened_at' => 'datetime',
        'resolved_at' => 'datetime',
    ];

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function isResolved(): bool
    {
        return $this->status->isResolved();
    }
}
