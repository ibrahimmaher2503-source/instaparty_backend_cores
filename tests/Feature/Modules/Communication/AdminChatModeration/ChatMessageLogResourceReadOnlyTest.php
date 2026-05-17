<?php

declare(strict_types=1);

use App\Modules\Communication\Domain\Models\ChatThread;
use App\Modules\Communication\Filament\Resources\ChatThreadResource;
use App\Modules\Identity\Domain\Models\User;
use Database\Seeders\IdentityRolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $this->seed(IdentityRolesSeeder::class);

    foreach ([
        'chat_moderation.view',
    ] as $permission) {
        Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
    }
    Role::findByName('admin', 'web')->givePermissionTo('chat_moderation.view');
    app(PermissionRegistrar::class)->forgetCachedPermissions();
});

it('only exposes index and view pages on ChatThreadResource (no create/edit)', function (): void {
    $pages = array_keys(ChatThreadResource::getPages());

    expect($pages)->toContain('index');
    expect($pages)->toContain('view');
    expect($pages)->not->toContain('create');
    expect($pages)->not->toContain('edit');
})->group('chat_moderation', 'communication');

it('returns 200 for ChatThreadResource index URL', function (): void {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $indexUrl = ChatThreadResource::getUrl('index');

    $this->actingAs($admin)
        ->get($indexUrl)
        ->assertSuccessful();
})->group('chat_moderation', 'communication');

it('returns 200 for ChatThreadResource view URL', function (): void {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $thread = ChatThread::factory()->create();

    $viewUrl = ChatThreadResource::getUrl('view', ['record' => $thread]);

    $this->actingAs($admin)
        ->get($viewUrl)
        ->assertSuccessful();
})->group('chat_moderation', 'communication');

it('does not expose ChatThreadResource create or edit URLs', function (): void {
    // getUrl('create') / getUrl('edit') should throw because pages are not registered.
    expect(fn () => ChatThreadResource::getUrl('create'))
        ->toThrow(\Exception::class);
    expect(fn () => ChatThreadResource::getUrl('edit', ['record' => 1]))
        ->toThrow(\Exception::class);
})->group('chat_moderation', 'communication');

it('confirms ChatMessageLogResource is read-only when present (skipped otherwise)', function (): void {
    $resourceClass = 'App\\Modules\\Communication\\Filament\\Resources\\ChatMessageLogResource';
    if (! class_exists($resourceClass)) {
        $this->markTestSkipped('ChatMessageLogResource not yet implemented (US6 — owned by another agent).');

        return;
    }

    $pages = array_keys($resourceClass::getPages());
    expect($pages)->not->toContain('create');
    expect($pages)->not->toContain('edit');
})->group('chat_moderation', 'communication');
