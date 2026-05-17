<?php

declare(strict_types=1);

use App\Modules\Communication\Application\Actions\ResolveChatFlagAction;
use App\Modules\Communication\Application\DTOs\ResolveChatFlagDTO;
use App\Modules\Communication\Domain\Enums\ChatFlagAction;
use App\Modules\Communication\Domain\Enums\ChatFlagResolution;
use App\Modules\Communication\Domain\Enums\ChatFlagType;
use App\Modules\Communication\Domain\Events\ChatModerationFlagResolved;
use App\Modules\Communication\Domain\Exceptions\ChatFlagAlreadyResolved;
use App\Modules\Communication\Domain\Models\ChatMessageLog;
use App\Modules\Communication\Domain\Models\ChatModerationFlag;
use App\Modules\Communication\Domain\Models\ChatThread;
use App\Modules\Communication\Http\Controllers\Admin\ResolveChatFlagController;
use App\Modules\Identity\Domain\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    // Ensure permissions exist (idempotent — seeder also handles this).
    foreach (['chat_moderation.resolve_flag'] as $perm) {
        Permission::firstOrCreate(['name' => $perm, 'guard_name' => 'web']);
    }

    // Register route locally for HTTP-layer tests (orchestrator merges into admin.php later).
    Route::middleware(['api', 'auth:sanctum'])
        ->prefix('api/v1/admin')
        ->group(function () {
            Route::post('chat-moderation-flags/{flag}/resolve', ResolveChatFlagController::class)
                ->middleware('can:chat_moderation.resolve_flag')
                ->name('test.chat-moderation-flags.resolve');
        });
});

function makeAdminWithPermission(string ...$permissions): User
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

function makeUnresolvedFlag(ChatFlagType $type = ChatFlagType::Phone): ChatModerationFlag
{
    $thread = ChatThread::factory()->create();

    $log = ChatMessageLog::factory()->flagged('phone_pattern')->create([
        'chat_thread_id' => $thread->id,
    ]);

    return ChatModerationFlag::factory()->create([
        'chat_message_log_id' => $log->id,
        'flag_type' => $type->value,
        'matched_pattern' => '+201001234567',
        'action_taken' => ChatFlagAction::Warn->value,
        'reviewed_at' => null,
    ]);
}

// ─────────────────────────────────────────────────────────────────────────────
// Action-level tests — happy paths for all 4 decisions
// ─────────────────────────────────────────────────────────────────────────────

it('resolves with upheld_redact and flips message_log.redacted=true without touching other columns', function () {
    Event::fake([ChatModerationFlagResolved::class]);
    $actor = User::factory()->create();
    $flag = makeUnresolvedFlag();

    $originalSenderId = $flag->messageLog->sender_id;
    $originalFirestoreId = $flag->messageLog->firestore_message_id;
    $originalMessageKind = $flag->messageLog->message_kind;

    $action = app(ResolveChatFlagAction::class);
    $resolved = $action->execute(
        $flag,
        new ResolveChatFlagDTO(ChatFlagResolution::UpheldRedact, 'note en here', 'ملاحظة عربية'),
        $actor,
    );

    expect($resolved->reviewed_at)->not->toBeNull();
    expect($resolved->reviewed_by)->toBe($actor->id);
    expect($resolved->action_taken)->toBe(ChatFlagAction::Redact);

    $log = $flag->messageLog->fresh();
    expect($log->redacted)->toBeTrue();
    // Inviolable: no content columns mutated.
    expect($log->sender_id)->toBe($originalSenderId);
    expect($log->firestore_message_id)->toBe($originalFirestoreId);
    expect($log->message_kind)->toBe($originalMessageKind);

    Event::assertDispatched(ChatModerationFlagResolved::class, 1);
})->group('chat-moderation', 'us4');

it('resolves with upheld_warn without redacting', function () {
    Event::fake([ChatModerationFlagResolved::class]);
    $actor = User::factory()->create();
    $flag = makeUnresolvedFlag();

    app(ResolveChatFlagAction::class)->execute(
        $flag,
        new ResolveChatFlagDTO(ChatFlagResolution::UpheldWarn, 'note en', 'ملاحظة عربية'),
        $actor,
    );

    expect($flag->messageLog->fresh()->redacted)->toBeFalse();
    expect($flag->fresh()->action_taken)->toBe(ChatFlagAction::Warn);
})->group('chat-moderation', 'us4');

it('resolves with upheld_block without redacting', function () {
    Event::fake([ChatModerationFlagResolved::class]);
    $actor = User::factory()->create();
    $flag = makeUnresolvedFlag();

    app(ResolveChatFlagAction::class)->execute(
        $flag,
        new ResolveChatFlagDTO(ChatFlagResolution::UpheldBlock, 'note en', 'ملاحظة عربية'),
        $actor,
    );

    expect($flag->messageLog->fresh()->redacted)->toBeFalse();
    expect($flag->fresh()->action_taken)->toBe(ChatFlagAction::Block);
})->group('chat-moderation', 'us4');

it('resolves with dismissed_false_positive without redacting', function () {
    Event::fake([ChatModerationFlagResolved::class]);
    $actor = User::factory()->create();
    $flag = makeUnresolvedFlag();

    app(ResolveChatFlagAction::class)->execute(
        $flag,
        new ResolveChatFlagDTO(ChatFlagResolution::DismissedFalsePositive, 'note en', 'ملاحظة عربية'),
        $actor,
    );

    expect($flag->messageLog->fresh()->redacted)->toBeFalse();
    expect($flag->fresh()->action_taken)->toBe(ChatFlagAction::None);
})->group('chat-moderation', 'us4');

it('throws ChatFlagAlreadyResolved when re-resolving a closed flag', function () {
    $actor = User::factory()->create();
    $flag = makeUnresolvedFlag();
    $flag->update([
        'reviewed_at' => now(),
        'reviewed_by' => $actor->id,
        'action_taken' => ChatFlagAction::Warn->value,
    ]);

    app(ResolveChatFlagAction::class)->execute(
        $flag,
        new ResolveChatFlagDTO(ChatFlagResolution::UpheldWarn, 'note en', 'ملاحظة عربية'),
        $actor,
    );
})->throws(ChatFlagAlreadyResolved::class)->group('chat-moderation', 'us4');

it('writes exactly one audit_logs row per successful resolution', function () {
    $actor = makeAdminWithPermission('chat_moderation.resolve_flag');
    $flag = makeUnresolvedFlag();

    $before = DB::table('audit_logs')->count();

    app(ResolveChatFlagAction::class)->execute(
        $flag,
        new ResolveChatFlagDTO(ChatFlagResolution::UpheldRedact, 'reviewed phone', 'تمت المراجعة'),
        $actor,
    );

    $after = DB::table('audit_logs')->count();
    expect($after - $before)->toBe(1);

    $row = DB::table('audit_logs')->latest('id')->first();
    expect($row->action)->toBe('chat.flag.resolved');
})->group('chat-moderation', 'us4');

// ─────────────────────────────────────────────────────────────────────────────
// HTTP-layer tests
// ─────────────────────────────────────────────────────────────────────────────

it('returns 409 when re-resolving via HTTP', function () {
    $actor = makeAdminWithPermission('chat_moderation.resolve_flag');
    $flag = makeUnresolvedFlag();
    $flag->update([
        'reviewed_at' => now(),
        'reviewed_by' => $actor->id,
        'action_taken' => ChatFlagAction::Warn->value,
    ]);

    $this->actingAs($actor)
        ->postJson("/api/v1/admin/chat-moderation-flags/{$flag->id}/resolve", [
            'decision' => 'upheld_warn',
            'note_en'  => 'note en here',
            'note_ar'  => 'ملاحظة عربية',
        ])
        ->assertStatus(409)
        ->assertJsonPath('errors.0.code', 'chat_flag_already_resolved');
})->group('chat-moderation', 'us4');

it('returns 422 when note_ar is missing', function () {
    $actor = makeAdminWithPermission('chat_moderation.resolve_flag');
    $flag = makeUnresolvedFlag();

    $this->actingAs($actor)
        ->postJson("/api/v1/admin/chat-moderation-flags/{$flag->id}/resolve", [
            'decision' => 'upheld_warn',
            'note_en'  => 'note en here',
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['note_ar']);
})->group('chat-moderation', 'us4');

it('returns 403 without chat_moderation.resolve_flag permission', function () {
    $actor = User::factory()->create();
    $flag = makeUnresolvedFlag();

    $this->actingAs($actor)
        ->postJson("/api/v1/admin/chat-moderation-flags/{$flag->id}/resolve", [
            'decision' => 'upheld_warn',
            'note_en'  => 'note en here',
            'note_ar'  => 'ملاحظة عربية',
        ])
        ->assertStatus(403);
})->group('chat-moderation', 'us4');
