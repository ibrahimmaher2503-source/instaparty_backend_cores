<?php

declare(strict_types=1);

use App\Modules\Communication\Domain\Models\ChatMessageLog;
use App\Modules\Communication\Domain\Models\ChatModerationFlag;
use App\Modules\Communication\Domain\Models\ChatThread;
use App\Modules\Communication\Filament\Resources\ChatThreadResource\Pages\ViewChatThread;
use App\Modules\Identity\Domain\Models\User;
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
    ] as $permission) {
        Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
    }
    Role::findByName('admin', 'web')->givePermissionTo('chat_moderation.view');
    app(PermissionRegistrar::class)->forgetCachedPermissions();
});

function insertAuditRow(string $type, int $id, ?int $userId, string $action, array $changes): void
{
    DB::table('audit_logs')->insert([
        'public_id' => Str::ulid()->toBase32(),
        'auditable_type' => $type,
        'auditable_id' => $id,
        'user_id' => $userId,
        'action' => $action,
        'changes' => json_encode($changes),
        'created_at' => now(),
    ]);
}

it('renders the four-event audit timeline in chronological order with no Edit/Delete buttons', function (): void {
    $admin = User::factory()->create(['name' => 'Audit Admin']);
    $admin->assignRole('admin');

    $thread = ChatThread::factory()->create();
    $log = ChatMessageLog::factory()->create([
        'chat_thread_id' => $thread->id,
        'sender_id' => $thread->customer_id,
    ]);
    $flag = ChatModerationFlag::factory()->create([
        'chat_message_log_id' => $log->id,
        'reviewed_at' => null,
    ]);

    $threadType = 'App\\Modules\\Communication\\Domain\\Models\\ChatThread';
    $flagType = 'App\\Modules\\Communication\\Domain\\Models\\ChatModerationFlag';

    insertAuditRow($threadType, $thread->id, $admin->id, 'chat.frozen', [
        'reason_en' => 'Suspicious phone shared',
        'reason_ar' => 'مشاركة رقم هاتف مشبوهة',
        'category' => 'off_platform_contact',
    ]);
    insertAuditRow($flagType, $flag->id, $admin->id, 'chat.flag.resolved', [
        'decision' => 'upheld_redact',
        'note_en' => 'Confirmed phone leak',
        'note_ar' => 'تأكيد تسريب الرقم',
    ]);
    insertAuditRow($threadType, $thread->id, $admin->id, 'chat.unfrozen', [
        'reason_en' => 'Investigation cleared',
        'reason_ar' => 'انتهى التحقيق',
    ]);
    insertAuditRow($flagType, $flag->id, $admin->id, 'chat.flag.escalated', [
        'severity' => 'warning',
        'summary_en' => 'Routed to inbox',
        'summary_ar' => 'تم التحويل إلى الصندوق',
    ]);

    $response = livewire(ViewChatThread::class, ['record' => $thread->getKey()])
        ->actingAs($admin)
        ->assertSuccessful();

    // Actor + bilingual reason fragments are present.
    $response
        ->assertSee('Audit Admin')
        ->assertSee('chat.frozen')
        ->assertSee('chat.flag.resolved')
        ->assertSee('chat.unfrozen')
        ->assertSee('chat.flag.escalated')
        ->assertSee('Suspicious phone shared')
        ->assertSee('مشاركة رقم هاتف مشبوهة')
        ->assertSee('Confirmed phone leak')
        ->assertSee('تأكيد تسريب الرقم')
        ->assertSee('Investigation cleared')
        ->assertSee('انتهى التحقيق')
        ->assertSee('Routed to inbox')
        ->assertSee('تم التحويل إلى الصندوق');

    // No Edit/Delete header actions ever rendered.
    $response
        ->assertDontSeeHtml('wire:click="mountAction(\'edit\')"')
        ->assertDontSeeHtml('wire:click="mountAction(\'delete\')"');
})->group('chat_moderation', 'communication');
