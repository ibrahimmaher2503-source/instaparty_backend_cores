<?php

declare(strict_types=1);

use App\Modules\Communication\Application\Actions\MarkOffPlatformContactAttemptAction;
use App\Modules\Communication\Application\DTOs\MarkOffPlatformContactDTO;
use App\Modules\Communication\Domain\Enums\ChatFlagAction;
use App\Modules\Communication\Domain\Enums\ChatFlagType;
use App\Modules\Communication\Domain\Events\OffPlatformContactMarked;
use App\Modules\Communication\Domain\Models\ChatMessageLog;
use App\Modules\Communication\Domain\Models\ChatModerationFlag;
use App\Modules\Communication\Domain\Models\ChatThread;
use App\Modules\Communication\Http\Controllers\Admin\MarkOffPlatformContactController;
use App\Modules\Identity\Domain\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Permission::firstOrCreate(['name' => 'chat_moderation.mark_off_platform', 'guard_name' => 'web']);

    Route::middleware(['api', 'auth:sanctum'])
        ->prefix('api/v1/admin')
        ->group(function () {
            Route::post('chat-message-logs/{log}/mark-off-platform', MarkOffPlatformContactController::class)
                ->middleware('can:chat_moderation.mark_off_platform')
                ->name('test.chat-message-logs.mark-off-platform');
        });
});

function makeAdminForMarkTest(string ...$permissions): User
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

function makeUnflaggedLog(): ChatMessageLog
{
    $thread = ChatThread::factory()->create();

    return ChatMessageLog::factory()->create([
        'chat_thread_id' => $thread->id,
        'flagged' => false,
        'flag_reason' => null,
    ]);
}

it('inserts a new flag with action_taken=block and flag_type=external_link', function () {
    Event::fake([OffPlatformContactMarked::class]);
    $actor = User::factory()->create();
    $log = makeUnflaggedLog();

    $originalSender = $log->sender_id;
    $originalFirestoreId = $log->firestore_message_id;

    $flag = app(MarkOffPlatformContactAttemptAction::class)->execute(
        $log,
        new MarkOffPlatformContactDTO($log->id, ChatFlagType::ExternalLink, 'reason en', 'سبب عربي'),
        $actor,
    );

    expect($flag->flag_type)->toBe(ChatFlagType::ExternalLink);
    expect($flag->action_taken)->toBe(ChatFlagAction::Block);

    $logFresh = $log->fresh();
    expect($logFresh->flagged)->toBeTrue();
    expect($logFresh->flag_reason)->toBe('manual');
    // Invariant: content columns unchanged.
    expect($logFresh->sender_id)->toBe($originalSender);
    expect($logFresh->firestore_message_id)->toBe($originalFirestoreId);

    Event::assertDispatched(OffPlatformContactMarked::class, 1);
})->group('chat-moderation', 'us6');

it('returns the existing flag idempotently when (log, flag_type) already exists', function () {
    Event::fake([OffPlatformContactMarked::class]);
    $actor = User::factory()->create();
    $log = makeUnflaggedLog();

    $first = app(MarkOffPlatformContactAttemptAction::class)->execute(
        $log,
        new MarkOffPlatformContactDTO($log->id, ChatFlagType::Phone, 'reason en', 'سبب عربي'),
        $actor,
    );

    $second = app(MarkOffPlatformContactAttemptAction::class)->execute(
        $log,
        new MarkOffPlatformContactDTO($log->id, ChatFlagType::Phone, 'reason en again', 'سبب عربي ثانٍ'),
        $actor,
    );

    expect($second->id)->toBe($first->id);
    expect(ChatModerationFlag::query()->where('chat_message_log_id', $log->id)->count())->toBe(1);
})->group('chat-moderation', 'us6');

it('writes one audit_logs row on new mark, zero on idempotent reuse', function () {
    $actor = User::factory()->create();
    $log = makeUnflaggedLog();

    $before = DB::table('audit_logs')->count();

    app(MarkOffPlatformContactAttemptAction::class)->execute(
        $log,
        new MarkOffPlatformContactDTO($log->id, ChatFlagType::Phone, 'reason en', 'سبب عربي'),
        $actor,
    );

    $mid = DB::table('audit_logs')->count();
    expect($mid - $before)->toBe(1);

    app(MarkOffPlatformContactAttemptAction::class)->execute(
        $log,
        new MarkOffPlatformContactDTO($log->id, ChatFlagType::Phone, 'reason en', 'سبب عربي'),
        $actor,
    );

    $after = DB::table('audit_logs')->count();
    expect($after - $mid)->toBe(0);
})->group('chat-moderation', 'us6');

it('returns 422 on invalid flag_type via HTTP', function () {
    $actor = makeAdminForMarkTest('chat_moderation.mark_off_platform');
    $log = makeUnflaggedLog();

    $this->actingAs($actor)
        ->postJson("/api/v1/admin/chat-message-logs/{$log->id}/mark-off-platform", [
            'flag_type' => 'profanity', // not allowed
            'reason_en' => 'reason en',
            'reason_ar' => 'سبب عربي',
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['flag_type']);
})->group('chat-moderation', 'us6');

it('returns 422 on missing bilingual reason', function () {
    $actor = makeAdminForMarkTest('chat_moderation.mark_off_platform');
    $log = makeUnflaggedLog();

    $this->actingAs($actor)
        ->postJson("/api/v1/admin/chat-message-logs/{$log->id}/mark-off-platform", [
            'flag_type' => 'phone',
            'reason_en' => 'reason en',
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['reason_ar']);
})->group('chat-moderation', 'us6');

it('returns 403 without chat_moderation.mark_off_platform permission', function () {
    $actor = User::factory()->create();
    $log = makeUnflaggedLog();

    $this->actingAs($actor)
        ->postJson("/api/v1/admin/chat-message-logs/{$log->id}/mark-off-platform", [
            'flag_type' => 'phone',
            'reason_en' => 'reason en',
            'reason_ar' => 'سبب عربي',
        ])
        ->assertStatus(403);
})->group('chat-moderation', 'us6');
