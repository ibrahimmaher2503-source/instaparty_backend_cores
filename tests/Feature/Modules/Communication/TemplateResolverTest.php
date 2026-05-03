<?php

declare(strict_types=1);

use App\Modules\Communication\Application\Services\TemplateNotFoundException;
use App\Modules\Communication\Application\Services\TemplateResolver;
use App\Modules\Communication\Domain\Enums\NotificationAudience;
use App\Modules\Communication\Domain\Enums\NotificationChannel;
use App\Modules\Communication\Domain\Models\NotificationTemplate;
use Illuminate\Support\Str;

beforeEach(function () {
    NotificationTemplate::query()->delete();
    $this->resolver = app(TemplateResolver::class);
});

it('returns body in requested locale', function () {
    NotificationTemplate::create([
        'public_id' => Str::ulid()->toBase32(),
        'event_key' => 'booking.submitted',
        'channel' => 'push',
        'audience' => 'customer',
        'body' => ['en' => 'Your booking was submitted.', 'ar' => 'تم تقديم حجزك.'],
        'subject' => null,
        'is_active' => true,
    ]);

    $result = $this->resolver->resolve('booking.submitted', NotificationChannel::Push, NotificationAudience::Customer, 'ar');
    expect($result->body)->toBe('تم تقديم حجزك.');
})->group('communication');

it('falls back to en body when requested locale missing', function () {
    NotificationTemplate::create([
        'public_id' => Str::ulid()->toBase32(),
        'event_key' => 'booking.submitted',
        'channel' => 'push',
        'audience' => 'customer',
        'body' => ['en' => 'Your booking was submitted.'],
        'subject' => null,
        'is_active' => true,
    ]);

    $result = $this->resolver->resolve('booking.submitted', NotificationChannel::Push, NotificationAudience::Customer, 'ar');
    expect($result->body)->toBe('Your booking was submitted.');
})->group('communication');

it('throws TemplateNotFoundException when no template exists', function () {
    $this->resolver->resolve('nonexistent.event', NotificationChannel::Push, NotificationAudience::Customer, 'en');
})->throws(TemplateNotFoundException::class)->group('communication');

it('throws TemplateNotFoundException when template is inactive', function () {
    NotificationTemplate::create([
        'public_id' => Str::ulid()->toBase32(),
        'event_key' => 'some.event',
        'channel' => 'push',
        'audience' => 'customer',
        'body' => ['en' => 'Body'],
        'is_active' => false,
    ]);

    $this->resolver->resolve('some.event', NotificationChannel::Push, NotificationAudience::Customer, 'en');
})->throws(TemplateNotFoundException::class)->group('communication');
