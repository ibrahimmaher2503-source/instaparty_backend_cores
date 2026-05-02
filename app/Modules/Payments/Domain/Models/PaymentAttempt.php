<?php

declare(strict_types=1);

namespace App\Modules\Payments\Domain\Models;

use Illuminate\Database\Eloquent\Model;

class PaymentAttempt extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'payment_id', 'attempt_no', 'request_payload', 'response_payload', 'http_status', 'created_at',
    ];

    protected $casts = [
        'request_payload' => 'array',
        'response_payload' => 'array',
        'created_at' => 'datetime',
    ];
}
