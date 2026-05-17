<?php

declare(strict_types=1);

use App\Modules\Communication\Filament\Resources\ChatThreadResource;
use App\Modules\Communication\Filament\Resources\ChatThreadResource\Pages\ListChatThreads;
use App\Modules\Identity\Domain\Models\User;
use Database\Seeders\IdentityRolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
});

it('hides ChatThreadResource from navigation when user lacks chat_moderation.view', function (): void {
    $user = User::factory()->create();
    // Assign a role with no chat_moderation.view permission.
    $bareRole = Role::firstOrCreate(['name' => 'bare_admin', 'guard_name' => 'web']);
    $user->assignRole($bareRole);

    $this->actingAs($user);

    expect(ChatThreadResource::canViewAny())->toBeFalse();
    expect(ChatThreadResource::shouldRegisterNavigation())->toBeFalse();
})->group('chat_moderation', 'communication');

it('shows ChatThreadResource in navigation when user has chat_moderation.view', function (): void {
    $admin = User::factory()->create();
    $admin->assignRole('admin');
    Role::findByName('admin', 'web')->givePermissionTo('chat_moderation.view');
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $this->actingAs($admin);

    expect(ChatThreadResource::canViewAny())->toBeTrue();
    expect(ChatThreadResource::shouldRegisterNavigation())->toBeTrue();
})->group('chat_moderation', 'communication');

it('returns 403 / forbidden when admin without chat_moderation.view loads the list page', function (): void {
    $user = User::factory()->create();
    $bareRole = Role::firstOrCreate(['name' => 'bare_admin_two', 'guard_name' => 'web']);
    $user->assignRole($bareRole);

    livewire(ListChatThreads::class)
        ->actingAs($user)
        ->assertForbidden();
})->group('chat_moderation', 'communication');
