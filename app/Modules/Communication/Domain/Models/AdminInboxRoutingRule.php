<?php

declare(strict_types=1);

namespace App\Modules\Communication\Domain\Models;

use App\Modules\Communication\Database\Factories\AdminInboxRoutingRuleFactory;
use App\Modules\Communication\Domain\Enums\AdminInboxSeverity;
use App\Modules\Identity\Domain\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Permission\Models\Role;

class AdminInboxRoutingRule extends Model
{
    use HasFactory;

    protected static function newFactory(): AdminInboxRoutingRuleFactory
    {
        return AdminInboxRoutingRuleFactory::new();
    }

    protected $fillable = [
        'public_id',
        'event_key',
        'severity',
        'route_to_role_id',
        'route_to_admin_id',
        'is_active',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'severity' => AdminInboxSeverity::class,
        'is_active' => 'boolean',
    ];

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class, 'route_to_role_id');
    }

    public function targetAdmin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'route_to_admin_id');
    }

    public function scopeActiveForEvent(Builder $query, string $eventKey, AdminInboxSeverity $severity): Builder
    {
        return $query
            ->where('event_key', $eventKey)
            ->where('severity', $severity->value)
            ->where('is_active', true);
    }
}
