<?php

declare(strict_types=1);

namespace App\Modules\Communication\Domain\Models;

use App\Modules\Communication\Database\Factories\AdminInboxItemFactory;
use App\Modules\Communication\Domain\Enums\AdminInboxSeverity;
use App\Modules\Communication\Domain\Enums\AdminInboxStatus;
use App\Modules\Identity\Domain\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Spatie\Translatable\HasTranslations;

class AdminInboxItem extends Model
{
    use HasFactory;
    use HasTranslations;

    protected static function newFactory(): AdminInboxItemFactory
    {
        return AdminInboxItemFactory::new();
    }

    protected array $translatable = ['title', 'body'];

    protected $fillable = [
        'public_id',
        'admin_id',
        'source_type',
        'source_id',
        'severity',
        'title',
        'body',
        'status',
        'snoozed_until',
        'assigned_to_admin_id',
    ];

    protected $casts = [
        'severity' => AdminInboxSeverity::class,
        'status' => AdminInboxStatus::class,
        'snoozed_until' => 'datetime',
    ];

    public function admin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'admin_id');
    }

    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to_admin_id');
    }

    public function source(): MorphTo
    {
        return $this->morphTo('source');
    }

    public function scopeForAdmin(Builder $query, int $adminId): Builder
    {
        return $query->where('admin_id', $adminId);
    }

    public function scopeUnread(Builder $query): Builder
    {
        return $query->where('status', AdminInboxStatus::Unread->value);
    }

    /** Items that should appear in the active inbox list. */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where(function (Builder $q) {
            $q->whereIn('status', AdminInboxStatus::activeStatuses())
                ->orWhere(function (Builder $inner) {
                    $inner->where('status', AdminInboxStatus::Snoozed->value)
                        ->where('snoozed_until', '<', now());
                });
        });
    }
}
