<?php

declare(strict_types=1);

namespace App\Modules\Booking\Domain\Models;

use App\Modules\Booking\Database\Factories\BookingStateTransitionFactory;
use App\Modules\Identity\Domain\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class BookingStateTransition extends Model
{
    use HasFactory;

    public const UPDATED_AT = null;

    protected $fillable = [
        'transitionable_type', 'transitionable_id',
        'from_state', 'to_state',
        'triggered_by', 'trigger_kind', 'context',
    ];

    protected $casts = [
        'context' => 'array',
    ];

    protected static function newFactory(): BookingStateTransitionFactory
    {
        return BookingStateTransitionFactory::new();
    }

    public function transitionable(): MorphTo
    {
        return $this->morphTo();
    }

    public function triggeredByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'triggered_by');
    }
}
