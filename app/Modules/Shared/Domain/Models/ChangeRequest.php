<?php

declare(strict_types=1);

namespace App\Modules\Shared\Domain\Models;

use App\Models\User;
use App\Modules\Shared\Domain\Enums\ChangeRequestStatus;
use App\Modules\Shared\Traits\HasPublicId;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class ChangeRequest extends Model
{
    use HasPublicId;

    protected $table = 'change_requests';

    public $timestamps = false;

    protected $casts = [
        'status' => ChangeRequestStatus::class,
        'cycle_number' => 'integer',
        'created_at' => 'datetime',
        'resolved_at' => 'datetime',
    ];

    protected $fillable = [
        'public_id',
        'subject_type',
        'subject_id',
        'requested_by_admin_id',
        'status',
        'cycle_number',
        'resolution_notes',
        'resolved_by_admin_id',
        'resolved_at',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(ChangeRequestItem::class, 'change_request_id');
    }

    public function subject(): MorphTo
    {
        return $this->morphTo('subject', 'subject_type', 'subject_id');
    }

    public function requestedByAdmin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by_admin_id');
    }

    public function resolvedByAdmin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by_admin_id');
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->where('status', ChangeRequestStatus::Open);
    }

    public function scopeResubmitted(Builder $query): Builder
    {
        return $query->where('status', ChangeRequestStatus::Resubmitted);
    }

    public function scopeForSubject(Builder $query, string $type, int $id): Builder
    {
        return $query->where('subject_type', $type)->where('subject_id', $id);
    }
}
