<?php

declare(strict_types=1);

use App\Modules\Communication\Application\Actions\EscalateChatFlagToAdminInboxAction;
use App\Modules\Communication\Application\DTOs\EscalateChatFlagDTO;
use App\Modules\Communication\Domain\Enums\ChatFlagAction;
use App\Modules\Communication\Domain\Enums\ChatFlagType;
use App\Modules\Communication\Domain\Events\ChatFlagEscalatedToInbox;
use App\Modules\Communication\Domain\Models\AdminInboxItem;
use App\Modules\Communication\Domain\Models\ChatMessageLog;
use App\Modules\Communication\Domain\Models\ChatModerationFlag;
use App\Modules\Communication\Domain\Models\ChatThread;
use App\Modules\Communication\Http\Controllers\Admin\EscalateChatFlagController;
use App\Modules\Identity\Domain\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Permission::firstOrCreate(['name' => 'chat_moderation.escalate', 'guard_name' => 'web']);

    Route::middleware(['api', 'auth:sanctum'])
        ->prefix('api/v1/admin')
        ->group(function () {
            Route::post('chat-moderation-flags/{flag}/escalate', EscalateChatFlagController::class)
                ->middleware('can:chat_moderation.escalate')
                ->name('test.chat-moderation-flags.escalate');
        });
});

function makeAdminForEscalateTest(string ...$permissions): User
{
    $role = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    foreach ($permissions as $p) {
        $perm = Permission::firstOrCreate(['name' => $p, 'guard_name' => 'web']);
        $role->givePermissionTo($perm);
    }
    $user = User::factory()->create();
    $user->assignRole($role);

    return $user;
}

function makeOpenFlag(): ChatModerationFlag
{
    $thread = ChatThread::factory()->create();

    $log = ChatMessageLog::factory()->create([
        'chat_thread_id' => $thread->id,
    ]);

    return ChatModerationFlag::factory()->create([
        'chat_message_log_id' => $log->id,
        'flag_type' => ChatFlagType::Phone->value,
        'matched_pattern' => '+201001234567',
        'action_taken' => ChatFlagAction::Warn->value,
    ]);
}

it('creates an admin_inbox_items row linked to the ChatModerationFlag', function () {
    Event::fake([ChatFlagEscalatedToInbox::class]);
    // Pre-create an admin user so router fallback finds one.
    $assigneeAdmin = makeAdminForEscalateTest('chat_moderation.escalate');
    $actor = User::factory()->create();
    $flag = makeOpenFlag();

    $item = app(EscalateChatFlagToAdminInboxAction::class)->execute(
        $flag,
        new EscalateChatFlagDTO($flag->id, 'warning', 'summary en here', 'الملخص العربي'),
        $actor,
    );

    expect($item->source_type)->toBe(ChatModerationFlag::class);
    expect($item->source_id)->toBe($flag->id);
    // Routing helper resolves to an admin user; with seeded admins present we
    // only assert it's a real admin, not specifically $assigneeAdmin.
    expect($item->admin_id)->toBeInt()->toBeGreaterThan(0);
    expect(User::role('admin')->whereKey($item->admin_id)->exists())->toBeTrue();

    Event::assertDispatched(ChatFlagEscalatedToInbox::class, 1);
})->group('chat-moderation', 'us6');

it('returns the existing inbox item on re-escalate idempotently', function () {
    Event::fake([ChatFlagEscalatedToInbox::class]);
    $assignee = makeAdminForEscalateTest('chat_moderation.escalate');
    $actor = User::factory()->create();
    $flag = makeOpenFlag();

    $first = app(EscalateChatFlagToAdminInboxAction::class)->execute(
        $flag,
        new EscalateChatFlagDTO($flag->id, 'warning', 'summary en', 'الملخص'),
        $actor,
    );

    $second = app(EscalateChatFlagToAdminInboxAction::class)->execute(
        $flag,
        new EscalateChatFlagDTO($flag->id, 'critical', 'updated summary', 'ملخص محدث'),
        $actor,
    );

    expect($second->id)->toBe($first->id);
    expect(AdminInboxItem::query()
        ->where('source_type', ChatModerationFlag::class)
        ->where('source_id', $flag->id)
        ->count())->toBe(1);
})->group('chat-moderation', 'us6');

it('writes one audit_logs row on new escalation, zero on reuse', function () {
    $assignee = makeAdminForEscalateTest('chat_moderation.escalate');
    $actor = User::factory()->create();
    $flag = makeOpenFlag();

    $before = DB::table('audit_logs')->count();

    app(EscalateChatFlagToAdminInboxAction::class)->execute(
        $flag,
        new EscalateChatFlagDTO($flag->id, 'warning', 'summary en', 'الملخص'),
        $actor,
    );

    $mid = DB::table('audit_logs')->count();
    expect($mid - $before)->toBe(1);

    app(EscalateChatFlagToAdminInboxAction::class)->execute(
        $flag,
        new EscalateChatFlagDTO($flag->id, 'warning', 'summary en', 'الملخص'),
        $actor,
    );

    expect(DB::table('audit_logs')->count() - $mid)->toBe(0);
})->group('chat-moderation', 'us6');

it('dispatches an in-app notification to the assignee admin', function () {
    Event::fake();
    $assignee = makeAdminForEscalateTest('chat_moderation.escalate');
    $actor = User::factory()->create();
    $flag = makeOpenFlag();

    $item = app(EscalateChatFlagToAdminInboxAction::class)->execute(
        $flag,
        new EscalateChatFlagDTO($flag->id, 'warning', 'summary en', 'الملخص'),
        $actor,
    );

    Event::assertDispatched(ChatFlagEscalatedToInbox::class, function (ChatFlagEscalatedToInbox $event) use ($item): bool {
        return $event->inboxItem->id === $item->id
            && (int) $event->inboxItem->admin_id > 0;
    });
})->group('chat-moderation', 'us6');

it('returns 422 on invalid severity via HTTP', function () {
    $actor = makeAdminForEscalateTest('chat_moderation.escalate');
    $flag = makeOpenFlag();

    $this->actingAs($actor)
        ->postJson("/api/v1/admin/chat-moderation-flags/{$flag->id}/escalate", [
            'severity'   => 'urgent',
            'summary_en' => 'summary en',
            'summary_ar' => 'الملخص',
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['severity']);
})->group('chat-moderation', 'us6');

it('returns 422 on missing bilingual summary via HTTP', function () {
    $actor = makeAdminForEscalateTest('chat_moderation.escalate');
    $flag = makeOpenFlag();

    $this->actingAs($actor)
        ->postJson("/api/v1/admin/chat-moderation-flags/{$flag->id}/escalate", [
            'severity'   => 'warning',
            'summary_en' => 'summary en',
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['summary_ar']);
})->group('chat-moderation', 'us6');

it('returns 403 without chat_moderation.escalate permission', function () {
    $actor = User::factory()->create();
    $flag = makeOpenFlag();

    $this->actingAs($actor)
        ->postJson("/api/v1/admin/chat-moderation-flags/{$flag->id}/escalate", [
            'severity'   => 'warning',
            'summary_en' => 'summary en',
            'summary_ar' => 'الملخص',
        ])
        ->assertStatus(403);
})->group('chat-moderation', 'us6');
