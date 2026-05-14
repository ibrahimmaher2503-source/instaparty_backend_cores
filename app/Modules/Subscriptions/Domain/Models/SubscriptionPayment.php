<?php

declare(strict_types=1);

namespace App\Modules\Subscriptions\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SubscriptionPayment extends Model
{
    protected $table = 'subscription_payments';

    // Insert-only: no timestamps
    public $timestamps = false;

    const CREATED_AT = 'created_at';

    protected $fillable = [
        'subscription_invoice_id',
        'attempt_no',
        'mode',
        'status',
        'gateway',
        'gateway_ref',
        'failure_code',
        'failure_reason',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(SubscriptionInvoice::class, 'subscription_invoice_id');
    }
}
