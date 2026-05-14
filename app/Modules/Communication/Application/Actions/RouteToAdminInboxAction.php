<?php

declare(strict_types=1);

namespace App\Modules\Communication\Application\Actions;

use App\Modules\Communication\Domain\Enums\AdminInboxSeverity;
use App\Modules\Communication\Domain\Enums\AdminInboxStatus;
use App\Modules\Communication\Domain\Models\AdminInboxItem;
use App\Modules\Communication\Domain\Models\AdminInboxRoutingRule;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

class RouteToAdminInboxAction
{
    /**
     * @param  array{en: string, ar: string}  $title
     * @param  array{en: string, ar: string}  $body
     */
    public function execute(
        string $eventKey,
        AdminInboxSeverity $severity,
        string $sourceType,
        int $sourceId,
        array $title,
        array $body,
    ): void {
        $rules = AdminInboxRoutingRule::query()
            ->activeForEvent($eventKey, $severity)
            ->get();

        if ($rules->isEmpty()) {
            return;
        }

        // Batch-load all referenced roles with their users in two queries.
        $roleIds = $rules->whereNotNull('route_to_role_id')->pluck('route_to_role_id')->unique();
        $roleUsers = $roleIds->isNotEmpty()
            ? Role::with('users')->whereIn('id', $roleIds)->get()->keyBy('id')
            : collect();

        $adminIds = collect();

        foreach ($rules as $rule) {
            if ($rule->route_to_admin_id !== null) {
                $adminIds->push($rule->route_to_admin_id);
            } elseif ($rule->route_to_role_id !== null && $roleUsers->has($rule->route_to_role_id)) {
                $roleUsers->get($rule->route_to_role_id)->users->each(fn ($u) => $adminIds->push($u->id));
            }
        }

        $adminIds = $adminIds->unique();

        if ($adminIds->isEmpty()) {
            return;
        }

        DB::transaction(function () use ($adminIds, $sourceType, $sourceId, $severity, $title, $body) {
            foreach ($adminIds as $adminId) {
                AdminInboxItem::query()->insertOrIgnore([[
                    'public_id' => Str::ulid()->toBase32(),
                    'admin_id' => $adminId,
                    'source_type' => $sourceType,
                    'source_id' => $sourceId,
                    'severity' => $severity->value,
                    'title' => json_encode($title),
                    'body' => json_encode($body),
                    'status' => AdminInboxStatus::Unread->value,
                    'snoozed_until' => null,
                    'assigned_to_admin_id' => null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]]);
            }
        });
    }
}
