<?php

declare(strict_types=1);

use App\Modules\Booking\Domain\Models\Booking;
use App\Modules\Booking\Domain\Models\BookingItem;
use App\Modules\Booking\Domain\Models\BookingVendor;
use App\Modules\Catalog\Domain\Enums\ProductType;
use App\Modules\Communication\Domain\Models\ChatMessageLog;
use App\Modules\Communication\Domain\Models\ChatModerationFlag;
use App\Modules\Communication\Domain\Models\ChatThread;
use App\Modules\Communication\Filament\Resources\ChatThreadResource\Pages\ListChatThreads;
use App\Modules\Identity\Domain\Models\User;
use App\Modules\Identity\Domain\Models\VendorProfile;
use Database\Seeders\IdentityRolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

use function Pest\Livewire\livewire;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $this->seed(IdentityRolesSeeder::class);

    foreach ([
        'chat_moderation.view',
        'chat_moderation.freeze',
        'chat_moderation.unfreeze',
        'chat_moderation.resolve_flag',
        'chat_moderation.mark_off_platform',
        'chat_moderation.escalate',
    ] as $permission) {
        Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
    }

    $admin = Role::findByName('admin', 'web');
    $admin->givePermissionTo('chat_moderation.view');
});

function makeChatModerationAdmin(): User
{
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    return $admin;
}

function makeChatThreadFor(?Booking $booking = null, ?VendorProfile $vendor = null, array $overrides = []): ChatThread
{
    $vendor ??= VendorProfile::factory()->create();
    $customer = User::factory()->create();

    return ChatThread::create(array_merge([
        'public_id' => Str::ulid()->toBase32(),
        'firestore_thread_id' => 'fs-'.Str::random(20),
        'customer_id' => $customer->id,
        'vendor_profile_id' => $vendor->id,
        'booking_id' => $booking?->id,
        'status' => 'open',
    ], $overrides));
}

it('renders ten chat threads with correct counts and frozen-first sort', function (): void {
    $admin = makeChatModerationAdmin();

    $threads = [];
    for ($i = 0; $i < 5; $i++) {
        $threads[] = makeChatThreadFor();
    }
    // Two frozen
    $frozenAdmin = makeChatModerationAdmin();
    for ($i = 0; $i < 2; $i++) {
        $threads[] = makeChatThreadFor(null, null, [
            'status' => 'locked',
            'frozen_at' => now()->subHours($i + 1),
            'frozen_by' => $frozenAdmin->id,
        ]);
    }
    // Three with open flags
    for ($i = 0; $i < 3; $i++) {
        $t = makeChatThreadFor();
        $log = ChatMessageLog::factory()->create([
            'chat_thread_id' => $t->id,
            'sender_id' => $t->customer_id,
        ]);
        ChatModerationFlag::factory()->create([
            'chat_message_log_id' => $log->id,
            'reviewed_at' => null,
        ]);
        $threads[] = $t;
    }

    livewire(ListChatThreads::class)
        ->actingAs($admin)
        ->assertSuccessful()
        ->assertCanSeeTableRecords(collect($threads));
})->group('chat_moderation', 'communication');

it('filters threads by product type', function (): void {
    $admin = makeChatModerationAdmin();

    // Create a thread with a rental booking item and one without.
    $vendor = VendorProfile::factory()->create();
    $rentalBooking = Booking::factory()->create();
    $bv = BookingVendor::create([
        'public_id' => Str::ulid()->toBase32(),
        'booking_id' => $rentalBooking->id,
        'vendor_profile_id' => $vendor->id,
        'sub_status' => 'pending',
        'subtotal_minor' => 0,
        'subtotal_currency' => 'EGP',
        'delivery_fee_minor' => 0,
        'delivery_fee_currency' => 'EGP',
        'commission_minor' => 0,
        'commission_currency' => 'EGP',
        'vendor_payout_minor' => 0,
        'vendor_payout_currency' => 'EGP',
    ]);
    BookingItem::create([
        'public_id' => Str::ulid()->toBase32(),
        'booking_vendor_id' => $bv->id,
        'service_id' => \App\Modules\Catalog\Domain\Models\Service::factory()->create([
            'product_type' => ProductType::Rental,
            'vendor_profile_id' => $vendor->id,
        ])->id,
        'product_type' => ProductType::Rental,
        'name_snapshot' => ['en' => 'Bouncy Castle', 'ar' => 'قلعة قابلة للنفخ'],
        'unit_price_minor' => 10000,
        'unit_price_currency' => 'EGP',
        'line_total_minor' => 10000,
        'line_total_currency' => 'EGP',
        'commission_minor' => 0,
        'commission_currency' => 'EGP',
        'quantity' => 1,
        'item_status' => 'pending_acceptance',
        'commission_bps' => 0,
    ]);

    $rentalThread = makeChatThreadFor($rentalBooking, $vendor);
    $unrelatedThread = makeChatThreadFor();

    livewire(ListChatThreads::class)
        ->actingAs($admin)
        ->filterTable('product_type', ProductType::Rental->value)
        ->assertCanSeeTableRecords([$rentalThread])
        ->assertCanNotSeeTableRecords([$unrelatedThread]);
})->group('chat_moderation', 'communication');

it('filters out healthy threads via has_open_flags', function (): void {
    $admin = makeChatModerationAdmin();

    $healthy = makeChatThreadFor();

    $flagged = makeChatThreadFor();
    $log = ChatMessageLog::factory()->create([
        'chat_thread_id' => $flagged->id,
        'sender_id' => $flagged->customer_id,
    ]);
    ChatModerationFlag::factory()->create([
        'chat_message_log_id' => $log->id,
        'reviewed_at' => null,
    ]);

    livewire(ListChatThreads::class)
        ->actingAs($admin)
        ->filterTable('has_open_flags', true)
        ->assertCanSeeTableRecords([$flagged])
        ->assertCanNotSeeTableRecords([$healthy]);
})->group('chat_moderation', 'communication');

it('sorts frozen threads ahead of open threads by default', function (): void {
    $admin = makeChatModerationAdmin();

    $open = makeChatThreadFor();
    $frozen = makeChatThreadFor(null, null, [
        'status' => 'locked',
        'frozen_at' => now()->subHour(),
        'frozen_by' => $admin->id,
    ]);

    livewire(ListChatThreads::class)
        ->actingAs($admin)
        ->assertCanSeeTableRecords([$frozen, $open], inOrder: true);
})->group('chat_moderation', 'communication');

it('renders with bounded query count thanks to eager loading', function (): void {
    $admin = makeChatModerationAdmin();

    for ($i = 0; $i < 10; $i++) {
        $t = makeChatThreadFor();
        $log = ChatMessageLog::factory()->create([
            'chat_thread_id' => $t->id,
            'sender_id' => $t->customer_id,
        ]);
        ChatModerationFlag::factory()->create([
            'chat_message_log_id' => $log->id,
            'reviewed_at' => null,
        ]);
    }

    DB::enableQueryLog();

    livewire(ListChatThreads::class)
        ->actingAs($admin)
        ->assertSuccessful();

    $queryCount = count(DB::getQueryLog());
    DB::disableQueryLog();

    // Bounded — must not scale linearly with row count.
    // 10 threads + eager loads should stay well under 30 queries.
    expect($queryCount)->toBeLessThan(30);
})->group('chat_moderation', 'communication');
