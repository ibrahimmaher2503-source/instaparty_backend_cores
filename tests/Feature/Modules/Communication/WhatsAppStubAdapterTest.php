<?php

declare(strict_types=1);

use App\Modules\Communication\Domain\Enums\DispatchStatus;
use App\Modules\Communication\Domain\Enums\NotificationChannel;
use App\Modules\Communication\Domain\Models\NotificationDispatch;
use App\Modules\Communication\Domain\Models\NotificationTemplate;
use App\Modules\Communication\Infrastructure\Gateways\WhatsAppStubAdapter;
use App\Modules\Identity\Domain\Models\User;
use Illuminate\Support\Str;

it('sets status to sent and provider to whatsapp_stub without calling external API', function () {
    $template = NotificationTemplate::create([
        'public_id' => Str::ulid()->toBase32(),
        'event_key' => 'test.event',
        'channel' => 'whatsapp',
        'audience' => 'customer',
        'body' => ['en' => 'Test body'],
        'is_active' => true,
    ]);

    $user = User::factory()->create();

    $dispatch = NotificationDispatch::create([
        'public_id' => Str::ulid()->toBase32(),
        'notification_template_id' => $template->id,
        'user_id' => $user->id,
        'channel' => NotificationChannel::Whatsapp->value,
        'locale' => 'en',
        'status' => DispatchStatus::Queued->value,
        'context' => ['body' => 'Test body'],
    ]);

    $adapter = new WhatsAppStubAdapter;
    $adapter->send($dispatch);

    $dispatch->refresh();
    expect($dispatch->provider)->toBe('whatsapp_stub')
        ->and($dispatch->status)->toBe(DispatchStatus::Sent)
        ->and($dispatch->sent_at)->not()->toBeNull();
})->group('communication');
