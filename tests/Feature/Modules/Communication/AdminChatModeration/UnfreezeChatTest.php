<?php

declare(strict_types=1);

use App\Modules\Booking\Domain\Enums\VendorSubStatus;
use App\Modules\Booking\Domain\Models\Booking;
use App\Modules\Booking\Domain\Models\BookingVendor;
use App\Modules\Communication\Application\Actions\FreezeChatAction;
use App\Modules\Communication\Application\Actions\UnfreezeChatAction;
use App\Modules\Communication\Application\DTOs\FreezeChatDTO;
use App\Modules\Communication\Application\DTOs\UnfreezeChatDTO;
use App\Modules\Communication\Database\Seeders\ChatModerationPermissionsSeeder;
use App\Modules\Communication\Domain\Enums\ChatFreezeCategory;
use App\Modules\Communication\Domain\Events\ChatThreadUnfrozen;
use App\Modules\Communication\Domain\Exceptions\ChatThreadUnfreezeForbidden;
use App\Modules\Communication\Domain\Models\ChatThread;
use App\Modules\Communication\Http\Controllers\Admin\UnfreezeChatController;
use App\Modules\Identity\Domain\Models\User;
use App\Modules\Identity\Domain\Models\VendorProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Role::findOrCreate('admin', 'web');
    Role::findOrCreate('customer', 'web');
    (new ChatModerationPermissionsSeeder())->run();

    Route::middleware(['api', 'auth:sanctum'])
        ->prefix('api/v1/admin')
        ->group(function () {
            Route::post('chat-threads/{thread}/unfreeze', UnfreezeChatController::class)
                ->middleware('can:chat_moderation.unfreeze');
        });
});

/**
 * Builds a frozen ChatThread linked to a Booking+BookingVendor with the
 * given sub_status, enabling the unfreeze action to evaluate the review window.
 */
function makeFrozenThreadWithBookingVendor(VendorSubStatus $subStatus): ChatThread
{
    $vendorProfile = VendorProfile::factory()->create();
    $booking = Booking::factory()->submitted()->create();
    BookingVendor::factory()->create([
        'booking_id'        => $booking->id,
        'vendor_profile_id' => $vendorProfile->id,
        'sub_status'        => $subStatus,
    ]);

    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $thread = ChatThread::factory()->create([
        'booking_id'        => $booking->id,
        'vendor_profile_id' => $vendorProfile->id,
    ]);

    // Freeze it first
    app(FreezeChatAction::class)->execute(
        $thread,
        new FreezeChatDTO('Setting up test', 'تجهيز الاختبار', ChatFreezeCategory::Other),
        $admin,
    );

    return $thread->fresh();
}

// ── Happy path within review window ──────────────────────────────────────────

it('unfreezes a locked thread within the pending review window', function (): void {
    $thread = makeFrozenThreadWithBookingVendor(VendorSubStatus::Pending);

    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $dto = new UnfreezeChatDTO(
        reasonEn: 'Investigation cleared — content was vendor venue address.',
        reasonAr: 'تبين أن المحتوى عنوان قاعة المورد.',
    );

    $result = app(UnfreezeChatAction::class)->execute($thread, $dto, $admin);

    $result->refresh();
    expect($result->status)->toBe('open');
    expect($result->frozen_at)->toBeNull();
    expect($result->frozen_by)->toBeNull();
})->group('chat-moderation', 'unfreeze');

it('writes exactly +1 audit_logs row on successful unfreeze', function (): void {
    $thread = makeFrozenThreadWithBookingVendor(VendorSubStatus::Modified);

    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $before = \DB::table('audit_logs')
        ->where('auditable_type', ChatThread::class)
        ->where('auditable_id', $thread->id)
        ->where('action', 'chat.unfrozen')
        ->count();

    $dto = new UnfreezeChatDTO('Cleared.', 'تم الإفراج.');
    app(UnfreezeChatAction::class)->execute($thread, $dto, $admin);

    $after = \DB::table('audit_logs')
        ->where('auditable_type', ChatThread::class)
        ->where('auditable_id', $thread->id)
        ->where('action', 'chat.unfrozen')
        ->count();

    expect($after - $before)->toBe(1);
})->group('chat-moderation', 'unfreeze');

// ── 409 when outside review window ──────────────────────────────────────────

it('throws ChatThreadUnfreezeForbidden when sub_status is accepted', function (): void {
    $thread = makeFrozenThreadWithBookingVendor(VendorSubStatus::Accepted);

    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $dto = new UnfreezeChatDTO('Trying anyway.', 'محاولة على أي حال.');

    expect(fn () => app(UnfreezeChatAction::class)->execute($thread, $dto, $admin))
        ->toThrow(ChatThreadUnfreezeForbidden::class);
})->group('chat-moderation', 'unfreeze');

it('returns 409 over HTTP when sub_status is accepted', function (): void {
    $thread = makeFrozenThreadWithBookingVendor(VendorSubStatus::Accepted);

    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $response = $this->actingAs($admin, 'sanctum')->postJson(
        "/api/v1/admin/chat-threads/{$thread->id}/unfreeze",
        [
            'reason_en' => 'Trying after window closed.',
            'reason_ar' => 'محاولة بعد إغلاق النافذة.',
        ],
    );

    $response->assertStatus(409);
    $response->assertJsonStructure(['data', 'meta', 'errors']);
})->group('chat-moderation', 'unfreeze');

// ── Idempotency ──────────────────────────────────────────────────────────────

it('is idempotent: re-unfreezing an already-open thread writes no audit row and fires no event', function (): void {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $thread = ChatThread::factory()->create(); // never frozen

    Event::fake([ChatThreadUnfrozen::class]);

    $auditBefore = \DB::table('audit_logs')
        ->where('auditable_type', ChatThread::class)
        ->where('auditable_id', $thread->id)
        ->where('action', 'chat.unfrozen')
        ->count();

    $dto = new UnfreezeChatDTO('Already open.', 'مفتوحة بالفعل.');
    app(UnfreezeChatAction::class)->execute($thread, $dto, $admin);

    Event::assertNotDispatched(ChatThreadUnfrozen::class);

    $auditAfter = \DB::table('audit_logs')
        ->where('auditable_type', ChatThread::class)
        ->where('auditable_id', $thread->id)
        ->where('action', 'chat.unfrozen')
        ->count();

    expect($auditAfter)->toBe($auditBefore);
})->group('chat-moderation', 'unfreeze');

// ── HTTP-level validation ────────────────────────────────────────────────────

it('returns 422 when reason_ar is missing', function (): void {
    $thread = makeFrozenThreadWithBookingVendor(VendorSubStatus::Pending);

    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $response = $this->actingAs($admin, 'sanctum')->postJson(
        "/api/v1/admin/chat-threads/{$thread->id}/unfreeze",
        [
            'reason_en' => 'Reason only in English.',
        ],
    );

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['reason_ar']);
})->group('chat-moderation', 'unfreeze');

it('returns 403 when user lacks chat_moderation.unfreeze permission', function (): void {
    $thread = makeFrozenThreadWithBookingVendor(VendorSubStatus::Pending);

    $user = User::factory()->create();
    $user->assignRole('customer');

    $response = $this->actingAs($user, 'sanctum')->postJson(
        "/api/v1/admin/chat-threads/{$thread->id}/unfreeze",
        [
            'reason_en' => 'Anything goes.',
            'reason_ar' => 'أي محتوى.',
        ],
    );

    $response->assertStatus(403);
})->group('chat-moderation', 'unfreeze');
