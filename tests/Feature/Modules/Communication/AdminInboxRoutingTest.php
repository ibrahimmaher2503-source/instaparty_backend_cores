<?php

declare(strict_types=1);

use App\Modules\Communication\Application\Actions\AcknowledgeInboxItemAction;
use App\Modules\Communication\Application\Actions\BatchResolveInboxItemsAction;
use App\Modules\Communication\Application\Actions\ReassignInboxItemAction;
use App\Modules\Communication\Application\Actions\ResolveInboxItemAction;
use App\Modules\Communication\Application\Actions\RouteToAdminInboxAction;
use App\Modules\Communication\Application\Actions\SnoozeInboxItemAction;
use App\Modules\Communication\Application\Listeners\OnVendorRegisteredInbox;
use App\Modules\Communication\Domain\Enums\AdminInboxSeverity;
use App\Modules\Communication\Domain\Enums\AdminInboxStatus;
use App\Modules\Communication\Domain\Models\AdminInboxItem;
use App\Modules\Communication\Domain\Models\AdminInboxRoutingRule;
use App\Modules\Identity\Domain\Events\VendorRegistered;
use App\Modules\Identity\Domain\Models\User;
use App\Modules\Identity\Domain\Models\VendorProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

// ─────────────────────────────────────────────────────────────────────────────
// P1 — Vendor registration creates inbox items for vendor-manager role
// ─────────────────────────────────────────────────────────────────────────────

it('routes vendor.registered to all vendor-manager admins', function () {
    $admin1 = makeAdminWithRole('vendor_manager');
    $admin2 = makeAdminWithRole('vendor_manager');
    $otherAdmin = makeAdminWithRole('ops_manager');

    $role = Role::findByName('vendor_manager');
    makeRoutingRule('vendor.registered', AdminInboxSeverity::Info, $role->id);

    $vendorProfile = VendorProfile::factory()->create();

    app(OnVendorRegisteredInbox::class)->handle(new VendorRegistered($vendorProfile));

    expect(AdminInboxItem::query()->where('admin_id', $admin1->id)->count())->toBe(1);
    expect(AdminInboxItem::query()->where('admin_id', $admin2->id)->count())->toBe(1);
    expect(AdminInboxItem::query()->where('admin_id', $otherAdmin->id)->count())->toBe(0);

    $item = AdminInboxItem::query()->where('admin_id', $admin1->id)->first();
    expect($item->status)->toBe(AdminInboxStatus::Unread);
    expect($item->source_type)->toBe('vendor_profile');
    expect($item->source_id)->toBe($vendorProfile->id);
})->group('admin-inbox');

it('does not create inbox items when no active rule matches', function () {
    $admin = makeAdminWithRole('vendor_manager');

    // No routing rule created
    $vendorProfile = VendorProfile::factory()->create();
    app(OnVendorRegisteredInbox::class)->handle(new VendorRegistered($vendorProfile));

    expect(AdminInboxItem::query()->count())->toBe(0);
})->group('admin-inbox');

it('does not create inbox items when routing rule is inactive', function () {
    $role = Role::firstOrCreate(['name' => 'vendor_manager', 'guard_name' => 'web']);
    $admin = makeAdminWithRole('vendor_manager');

    AdminInboxRoutingRule::create([
        'public_id' => Str::ulid()->toBase32(),
        'event_key' => 'vendor.registered',
        'severity' => AdminInboxSeverity::Info->value,
        'route_to_role_id' => $role->id,
        'is_active' => false,
    ]);

    $vendorProfile = VendorProfile::factory()->create();
    app(OnVendorRegisteredInbox::class)->handle(new VendorRegistered($vendorProfile));

    expect(AdminInboxItem::query()->count())->toBe(0);
})->group('admin-inbox');

it('does not create duplicate items for the same source + admin from overlapping rules', function () {
    $role = Role::firstOrCreate(['name' => 'vendor_manager', 'guard_name' => 'web']);
    $admin = makeAdminWithRole('vendor_manager');

    // Two active rules for the same event key pointing to the same role
    makeRoutingRule('vendor.registered', AdminInboxSeverity::Info, $role->id);
    makeRoutingRule('vendor.registered', AdminInboxSeverity::Info, $role->id);

    $vendorProfile = VendorProfile::factory()->create();
    app(RouteToAdminInboxAction::class)->execute(
        eventKey: 'vendor.registered',
        severity: AdminInboxSeverity::Info,
        sourceType: 'vendor_profile',
        sourceId: $vendorProfile->id,
        title: ['en' => 'Test', 'ar' => 'اختبار'],
        body: ['en' => 'Body', 'ar' => 'النص'],
    );

    // UNIQUE (source_type, source_id, admin_id) constraint — only 1 item created
    expect(AdminInboxItem::query()->where('admin_id', $admin->id)->count())->toBe(1);
})->group('admin-inbox');

// ─────────────────────────────────────────────────────────────────────────────
// P1 — Acknowledge and resolve flow with audit logs
// ─────────────────────────────────────────────────────────────────────────────

it('transitions item to read on acknowledge', function () {
    $admin = makeAdminWithRole('vendor_manager');
    $item = AdminInboxItem::factory()->unread()->create(['admin_id' => $admin->id]);

    app(AcknowledgeInboxItemAction::class)->execute($item, $admin);

    expect($item->fresh()->status)->toBe(AdminInboxStatus::Read);
})->group('admin-inbox');

it('transitions item to resolved and writes audit log', function () {
    $admin = makeAdminWithRole('vendor_manager');
    $item = AdminInboxItem::factory()->unread()->create(['admin_id' => $admin->id]);

    app(ResolveInboxItemAction::class)->execute($item, $admin);

    expect($item->fresh()->status)->toBe(AdminInboxStatus::Resolved);

    $this->assertDatabaseHas('activity_log', [
        'log_name' => 'default',
        'description' => 'inbox_item_resolved',
        'subject_id' => $item->id,
        'causer_id' => $admin->id,
    ]);
})->group('admin-inbox');

// ─────────────────────────────────────────────────────────────────────────────
// P2 — Snooze: item hidden until snoozed_until passes
// ─────────────────────────────────────────────────────────────────────────────

it('snooze sets status and snoozed_until timestamp', function () {
    $admin = makeAdminWithRole('vendor_manager');
    $item = AdminInboxItem::factory()->unread()->create(['admin_id' => $admin->id]);

    app(SnoozeInboxItemAction::class)->execute($item, $admin, 4);

    $fresh = $item->fresh();
    expect($fresh->status)->toBe(AdminInboxStatus::Snoozed);
    expect($fresh->snoozed_until)->not()->toBeNull();
    expect($fresh->snoozed_until->isAfter(now()->addHours(3)))->toBeTrue();
})->group('admin-inbox');

it('snoozed item is excluded from active scope before expiry', function () {
    $admin = makeAdminWithRole('vendor_manager');
    $item = AdminInboxItem::factory()->snoozed(4)->create(['admin_id' => $admin->id]);

    $active = AdminInboxItem::query()->forAdmin($admin->id)->active()->get();

    expect($active->pluck('id'))->not()->toContain($item->id);
})->group('admin-inbox');

it('snoozed item reappears in active scope after snoozed_until passes', function () {
    $admin = makeAdminWithRole('vendor_manager');
    $item = AdminInboxItem::factory()->snoozedExpired()->create(['admin_id' => $admin->id]);

    $active = AdminInboxItem::query()->forAdmin($admin->id)->active()->get();

    expect($active->pluck('id'))->toContain($item->id);
})->group('admin-inbox');

it('wakeup command resets expired snoozed items to unread', function () {
    $admin = makeAdminWithRole('vendor_manager');
    $expired = AdminInboxItem::factory()->snoozedExpired()->create(['admin_id' => $admin->id]);
    $notExpired = AdminInboxItem::factory()->snoozed(4)->create(['admin_id' => $admin->id]);

    $this->artisan('inbox:wakeup-snoozed')->assertSuccessful();

    expect($expired->fresh()->status)->toBe(AdminInboxStatus::Unread);
    expect($expired->fresh()->snoozed_until)->toBeNull();
    expect($notExpired->fresh()->status)->toBe(AdminInboxStatus::Snoozed);
})->group('admin-inbox');

// ─────────────────────────────────────────────────────────────────────────────
// P2 — Reassign: moves ownership and audit-logs
// ─────────────────────────────────────────────────────────────────────────────

it('reassign creates new item for target admin and marks original reassigned', function () {
    $adminA = makeAdminWithRole('vendor_manager');
    $adminB = makeAdminWithRole('ops_manager');
    $item = AdminInboxItem::factory()->unread()->create(['admin_id' => $adminA->id]);

    $newItem = app(ReassignInboxItemAction::class)->execute($item, $adminA, $adminB);

    expect($item->fresh()->status)->toBe(AdminInboxStatus::Reassigned);
    expect($item->fresh()->assigned_to_admin_id)->toBe($adminB->id);

    expect($newItem->admin_id)->toBe($adminB->id);
    expect($newItem->status)->toBe(AdminInboxStatus::Unread);
    expect($newItem->source_type)->toBe($item->source_type);
    expect($newItem->source_id)->toBe($item->source_id);
})->group('admin-inbox');

it('reassign writes an audit log row', function () {
    $adminA = makeAdminWithRole('vendor_manager');
    $adminB = makeAdminWithRole('ops_manager');
    $item = AdminInboxItem::factory()->unread()->create(['admin_id' => $adminA->id]);

    $newItem = app(ReassignInboxItemAction::class)->execute($item, $adminA, $adminB);

    $this->assertDatabaseHas('activity_log', [
        'description' => 'inbox_item_reassigned',
        'subject_id' => $newItem->id,
        'causer_id' => $adminA->id,
    ]);
})->group('admin-inbox');

it('reassign throws when target user is not an admin', function () {
    $adminA = makeAdminWithRole('vendor_manager');
    $nonAdmin = User::factory()->create();
    $item = AdminInboxItem::factory()->unread()->create(['admin_id' => $adminA->id]);

    expect(fn () => app(ReassignInboxItemAction::class)->execute($item, $adminA, $nonAdmin))
        ->toThrow(InvalidArgumentException::class);
})->group('admin-inbox');

// ─────────────────────────────────────────────────────────────────────────────
// P3 — Batch resolve
// ─────────────────────────────────────────────────────────────────────────────

it('batch resolve resolves only items owned by the actor', function () {
    $adminA = makeAdminWithRole('vendor_manager');
    $adminB = makeAdminWithRole('ops_manager');

    $ownItems = AdminInboxItem::factory()->unread()->count(3)->create(['admin_id' => $adminA->id]);
    $othersItem = AdminInboxItem::factory()->unread()->create(['admin_id' => $adminB->id]);

    $ids = $ownItems->pluck('id')->merge([$othersItem->id])->all();

    $count = app(BatchResolveInboxItemsAction::class)->execute($ids, $adminA);

    expect($count)->toBe(3);

    foreach ($ownItems as $item) {
        expect($item->fresh()->status)->toBe(AdminInboxStatus::Resolved);
    }

    expect($othersItem->fresh()->status)->toBe(AdminInboxStatus::Unread);
})->group('admin-inbox');
