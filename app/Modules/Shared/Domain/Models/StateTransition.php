<?php

declare(strict_types=1);

namespace App\Modules\Shared\Domain\Models;

use App\Modules\Identity\Domain\Models\User;
use App\Modules\Shared\Database\Factories\StateTransitionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class StateTransition extends Model
{
    use HasFactory;

    protected $table = 'state_transitions';

    protected static function newFactory(): StateTransitionFactory
    {
        return StateTransitionFactory::new();
    }

    public const UPDATED_AT = null;

    protected $fillable = [
        'transitionable_type',
        'transitionable_id',
        'from_state',
        'to_state',
        'triggered_by',
        'trigger_kind',
        'reason',
        'trace_id',
        'context',
    ];

    protected $casts = [
        'context' => 'array',
    ];

    public function transitionable(): MorphTo
    {
        return $this->morphTo();
    }

    public function triggeredByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'triggered_by');
    }
}
