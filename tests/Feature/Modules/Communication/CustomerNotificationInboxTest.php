<?php

declare(strict_types=1);

use App\Modules\Communication\Domain\Enums\NotificationChannel;
use App\Modules\Communication\Domain\Models\NotificationDispatch;
use App\Modules\Identity\Domain\Models\User;
use Database\Seeders\IdentityRolesSeeder;

/**
 * Phase 3 B2 — audit F17: customer in-app notifications inbox.
 */
beforeEach(function (): void {
    $this->seed(IdentityRolesSeeder::class);
    $this->customer = User::factory()->asCustomer()->create();
});

function inboxNotification(User $user, array $overrides = []): NotificationDispatch
{
    return NotificationDispatch::factory()->create(array_merge([
        'user_id' => $user->id,
        'channel' => NotificationChannel::InApp,
        'context' => ['subject' => 'Booking confirmed', 'body' => 'Your booking INP-1 is confirmed.'],
    ], $overrides));
}

it('lists only the customer own in-app notifications', function (): void {
    inboxNotification($this->customer);
    inboxNotification($this->customer, ['context' => ['subject' => 'Second', 'body' => 'Body 2']]);

    // Noise: other user's notification + own push-channel dispatch.
    inboxNotification(User::factory()->asCustomer()->create());
    NotificationDispatch::factory()->create(['user_id' => $this->customer->id, 'channel' => NotificationChannel::Push]);

    $response = $this->actingAs($this->customer)
        ->getJson('/api/v1/customer/notifications')
        ->assertStatus(200);

    expect($response->json('data'))->toHaveCount(2)
        ->and($response->json('data.0'))->toHaveKeys(['public_id', 'title', 'body', 'is_read', 'created_at'])
        ->and($response->json('data.0'))->not->toHaveKey('provider')
        ->and($response->json('data.0'))->not->toHaveKey('error_message');
})->group('communication', 'notifications');

it('returns the unread count and decrements it after mark-read', function (): void {
    $a = inboxNotification($this->customer);
    inboxNotification($this->customer);

    $this->actingAs($this->customer)
        ->getJson('/api/v1/customer/notifications/unread-count')
        ->assertStatus(200)
        ->assertJsonPath('data.unread_count', 2);

    $this->actingAs($this->customer)
        ->patchJson("/api/v1/customer/notifications/{$a->public_id}/mark-read")
        ->assertStatus(200)
        ->assertJsonPath('data.is_read', true);

    $this->actingAs($this->customer)
        ->getJson('/api/v1/customer/notifications/unread-count')
        ->assertStatus(200)
        ->assertJsonPath('data.unread_count', 1);
})->group('communication', 'notifications');

it('mark-read is idempotent — read_at does not move on replay', function (): void {
    $n = inboxNotification($this->customer);

    $this->actingAs($this->customer)->patchJson("/api/v1/customer/notifications/{$n->public_id}/mark-read")->assertStatus(200);
    $firstReadAt = $n->refresh()->read_at;

    $this->travel(2)->minutes();
    $this->actingAs($this->customer)->patchJson("/api/v1/customer/notifications/{$n->public_id}/mark-read")->assertStatus(200);

    expect($n->refresh()->read_at->equalTo($firstReadAt))->toBeTrue();
})->group('communication', 'notifications');

it('marks all notifications read in bulk', function (): void {
    inboxNotification($this->customer);
    inboxNotification($this->customer);

    $this->actingAs($this->customer)
        ->postJson('/api/v1/customer/notifications/mark-all-read')
        ->assertStatus(200);

    expect(NotificationDispatch::query()
        ->where('user_id', $this->customer->id)
        ->where('channel', NotificationChannel::InApp)
        ->whereNull('read_at')
        ->count())->toBe(0);
})->group('communication', 'notifications');

it('deletes an own notification', function (): void {
    $n = inboxNotification($this->customer);

    $this->actingAs($this->customer)
        ->deleteJson("/api/v1/customer/notifications/{$n->public_id}")
        ->assertStatus(200);

    expect(NotificationDispatch::query()->where('public_id', $n->public_id)->exists())->toBeFalse();
})->group('communication', 'notifications');

it('cannot mark or delete another customer notification — 404', function (): void {
    $other = User::factory()->asCustomer()->create();
    $foreign = inboxNotification($other);

    $this->actingAs($this->customer)
        ->patchJson("/api/v1/customer/notifications/{$foreign->public_id}/mark-read")
        ->assertStatus(404);

    $this->actingAs($this->customer)
        ->deleteJson("/api/v1/customer/notifications/{$foreign->public_id}")
        ->assertStatus(404);

    expect(NotificationDispatch::query()->where('public_id', $foreign->public_id)->exists())->toBeTrue();
})->group('communication', 'notifications', 'privacy');

it('requires authentication', function (): void {
    $this->getJson('/api/v1/customer/notifications')->assertStatus(401);
    $this->postJson('/api/v1/customer/notifications/mark-all-read')->assertStatus(401);
})->group('communication', 'notifications');

it('vendor token gets 403 on the inbox', function (): void {
    $vendorUser = User::factory()->asVendor()->create();

    $this->actingAs($vendorUser)
        ->getJson('/api/v1/customer/notifications')
        ->assertStatus(403);
})->group('communication', 'notifications', 'authorization');
