<?php

declare(strict_types=1);

namespace App\Modules\Booking\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class BookingStateTransition extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'transitionable_type', 'transitionable_id',
        'from_state', 'to_state',
        'triggered_by', 'trigger_kind', 'context',
    ];

    protected $casts = [
        'context' => 'array',
    ];

    public function transitionable(): MorphTo
    {
        return $this->morphTo();
    }
}
