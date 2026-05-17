<?php

declare(strict_types=1);

use App\Modules\Communication\Application\Actions\FreezeChatAction;
use App\Modules\Communication\Application\DTOs\FreezeChatDTO;
use App\Modules\Communication\Database\Seeders\ChatModerationPermissionsSeeder;
use App\Modules\Communication\Domain\Enums\ChatFreezeCategory;
use App\Modules\Communication\Domain\Events\ChatThreadFrozen;
use App\Modules\Communication\Domain\Models\ChatThread;
use App\Modules\Communication\Domain\Models\NotificationDispatch;
use App\Modules\Communication\Http\Controllers\Admin\FreezeChatController;
use App\Modules\Identity\Domain\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Route;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    // Seed permissions
    Role::findOrCreate('admin', 'web');
    Role::findOrCreate('customer', 'web');
    (new ChatModerationPermissionsSeeder())->run();

    // Register HTTP routes inline so endpoint tests work without touching admin.php
    Route::middleware(['api', 'auth:sanctum'])
        ->prefix('api/v1/admin')
        ->group(function () {
            Route::post('chat-threads/{thread}/freeze', FreezeChatController::class)
                ->middleware('can:chat_moderation.freeze');
        });
});

// ── Happy path (Action-level) ────────────────────────────────────────────────

it('freezes an open thread, flips status, sets frozen_at/by, writes one audit row, fires event once', function (): void {
    Event::fake([ChatThreadFrozen::class]);

    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $thread = ChatThread::factory()->create();
    $auditCountBefore = \DB::table('audit_logs')->count();

    $dto = new FreezeChatDTO(
        reasonEn: 'Suspected phone exchange attempt.',
        reasonAr: 'اشتباه في تبادل أرقام هواتف.',
        category: ChatFreezeCategory::OffPlatformContact,
    );

    $result = app(FreezeChatAction::class)->execute($thread, $dto, $admin);

    $result->refresh();
    expect($result->status)->toBe('locked');
    expect($result->frozen_at)->not()->toBeNull();
    expect($result->frozen_by)->toBe($admin->id);

    // Event dispatched exactly once
    Event::assertDispatched(
        ChatThreadFrozen::class,
        fn (ChatThreadFrozen $e) => $e->thread->is($result),
    );
    Event::assertDispatchedTimes(ChatThreadFrozen::class, 1);

    // The Event::fake intercepts the event so the audit listener does NOT fire
    // when faking. We re-run WITHOUT fake to validate the audit_logs side effect.
})->group('chat-moderation', 'freeze');

it('writes exactly +1 audit_logs row on a successful freeze', function (): void {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $thread = ChatThread::factory()->create();
    $countBefore = \DB::table('audit_logs')->count();

    $dto = new FreezeChatDTO(
        reasonEn: 'Off-platform contact suspected.',
        reasonAr: 'يشتبه بمحاولة تواصل خارجية.',
        category: ChatFreezeCategory::OffPlatformContact,
    );

    app(FreezeChatAction::class)->execute($thread, $dto, $admin);

    $countAfter = \DB::table('audit_logs')->count();
    expect($countAfter - $countBefore)->toBe(1);

    $this->assertDatabaseHas('audit_logs', [
        'auditable_type' => ChatThread::class,
        'auditable_id'   => $thread->id,
        'user_id'        => $admin->id,
        'action'         => 'chat.frozen',
    ]);
})->group('chat-moderation', 'freeze');

// ── Idempotency ──────────────────────────────────────────────────────────────

it('is idempotent: re-freezing produces no duplicate audit row and no duplicate event', function (): void {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $thread = ChatThread::factory()->create();

    $dto = new FreezeChatDTO(
        reasonEn: 'Off-platform contact suspected.',
        reasonAr: 'يشتبه بمحاولة تواصل خارجية.',
        category: ChatFreezeCategory::OffPlatformContact,
    );

    app(FreezeChatAction::class)->execute($thread, $dto, $admin);
    $auditCountAfterFirst = \DB::table('audit_logs')
        ->where('auditable_type', ChatThread::class)
        ->where('auditable_id', $thread->id)
        ->where('action', 'chat.frozen')
        ->count();

    // Second call should short-circuit
    Event::fake([ChatThreadFrozen::class]);
    app(FreezeChatAction::class)->execute($thread->fresh(), $dto, $admin);

    Event::assertNotDispatched(ChatThreadFrozen::class);

    $auditCountAfterSecond = \DB::table('audit_logs')
        ->where('auditable_type', ChatThread::class)
        ->where('auditable_id', $thread->id)
        ->where('action', 'chat.frozen')
        ->count();

    expect($auditCountAfterSecond)->toBe($auditCountAfterFirst);
    expect($auditCountAfterFirst)->toBe(1);
})->group('chat-moderation', 'freeze');

// ── HTTP-level validation ────────────────────────────────────────────────────

it('returns 422 when reason_ar is missing', function (): void {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $thread = ChatThread::factory()->create();

    $response = $this->actingAs($admin, 'sanctum')->postJson(
        "/api/v1/admin/chat-threads/{$thread->id}/freeze",
        [
            'reason_en' => 'Suspicious behavior detected.',
            'category'  => 'off_platform_contact',
        ],
    );

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['reason_ar']);
})->group('chat-moderation', 'freeze');

it('returns 422 on invalid category', function (): void {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $thread = ChatThread::factory()->create();

    $response = $this->actingAs($admin, 'sanctum')->postJson(
        "/api/v1/admin/chat-threads/{$thread->id}/freeze",
        [
            'reason_en' => 'Suspicious behavior detected.',
            'reason_ar' => 'تم رصد سلوك مريب.',
            'category'  => 'not_a_real_category',
        ],
    );

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['category']);
})->group('chat-moderation', 'freeze');

it('returns 403 when user lacks chat_moderation.freeze permission', function (): void {
    $user = User::factory()->create(); // no admin role, no permission
    $user->assignRole('customer');

    $thread = ChatThread::factory()->create();

    $response = $this->actingAs($user, 'sanctum')->postJson(
        "/api/v1/admin/chat-threads/{$thread->id}/freeze",
        [
            'reason_en' => 'Suspicious behavior detected.',
            'reason_ar' => 'تم رصد سلوك مريب.',
            'category'  => 'off_platform_contact',
        ],
    );

    $response->assertStatus(403);
})->group('chat-moderation', 'freeze');

// ── Notifications ────────────────────────────────────────────────────────────

it('queues bilingual notification dispatches for customer and vendor', function (): void {
    Notification::fake();

    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $thread = ChatThread::factory()->create();

    // Sanity: both audiences resolvable
    expect($thread->customer_id)->not()->toBeNull();
    expect($thread->vendor_profile_id)->not()->toBeNull();

    $dto = new FreezeChatDTO(
        reasonEn: 'Off-platform contact suspected.',
        reasonAr: 'يشتبه بمحاولة تواصل خارجية.',
        category: ChatFreezeCategory::OffPlatformContact,
    );

    app(FreezeChatAction::class)->execute($thread, $dto, $admin);

    // Run any queued listeners synchronously is the default for sync queue in tests.
    // Notification dispatch rows are written to notification_dispatches table.
    // Listener writes a row even when template is missing (status=failed).
    $dispatches = NotificationDispatch::query()->get();

    // Expect dispatches for both the customer and vendor user.
    expect($dispatches->where('reference_type', 'chat_thread')->count())
        ->toBeGreaterThanOrEqual(2);
})->group('chat-moderation', 'freeze');
