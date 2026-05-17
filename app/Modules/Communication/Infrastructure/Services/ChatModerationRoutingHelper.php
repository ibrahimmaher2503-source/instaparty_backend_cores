<?php

declare(strict_types=1);

namespace App\Modules\Communication\Infrastructure\Services;

use App\Modules\Communication\Domain\Enums\AdminInboxSeverity;
use App\Modules\Communication\Domain\Models\AdminInboxRoutingRule;
use App\Modules\Communication\Domain\Models\ChatModerationFlag;
use App\Modules\Identity\Domain\Models\User;
use Spatie\Permission\Models\Role;

/**
 * Resolves the admin user_id who should receive an escalated chat moderation flag.
 *
 * Strategy:
 *  1. Match `admin_inbox_routing_rules` where `event_key = 'chat.moderation_flag_escalated'`
 *     and severity = warning (the default escalation severity). Most-specific rule wins:
 *       a. route_to_admin_id (specific admin) preferred
 *       b. route_to_role_id (first user with that role) fallback
 *  2. If no rule matches, fall back to the oldest user with the `admin` role.
 *  3. If no admin user exists, fall back to the oldest user id (last-resort).
 */
final class ChatModerationRoutingHelper
{
    private const EVENT_KEY = 'chat.moderation_flag_escalated';

    public function resolveAssignee(ChatModerationFlag $flag): int
    {
        $assigneeId = $this->resolveViaRoutingRules();

        if ($assigneeId !== null) {
            return $assigneeId;
        }

        return $this->fallbackToFirstAdminUserId();
    }

    private function resolveViaRoutingRules(): ?int
    {
        $rules = AdminInboxRoutingRule::query()
            ->activeForEvent(self::EVENT_KEY, AdminInboxSeverity::Warning)
            ->orderByDesc('id')
            ->get();

        foreach ($rules as $rule) {
            if ($rule->route_to_admin_id !== null) {
                return (int) $rule->route_to_admin_id;
            }

            if ($rule->route_to_role_id !== null) {
                $userId = Role::query()
                    ->whereKey($rule->route_to_role_id)
                    ->with(['users' => fn ($q) => $q->orderBy('id')])
                    ->first()
                    ?->users
                    ?->first()
                    ?->id;

                if ($userId !== null) {
                    return (int) $userId;
                }
            }
        }

        return null;
    }

    private function fallbackToFirstAdminUserId(): int
    {
        $admin = User::role('admin')->oldest('id')->first();

        if ($admin !== null) {
            return (int) $admin->id;
        }

        // Last-resort fallback — ensures the Action never throws on a clean DB.
        return (int) User::query()->oldest('id')->firstOrFail()->id;
    }
}
