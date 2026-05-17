<?php

declare(strict_types=1);

use App\Modules\Communication\Domain\Enums\ChatFlagType;
use App\Modules\Communication\Infrastructure\Services\MatchedPattern;
use App\Modules\Communication\Infrastructure\Services\MessagePatternDetector;

uses()->group('chat_moderation', 'unit');

beforeEach(function (): void {
    $this->detector = new MessagePatternDetector();
});

it('detects Egyptian carrier phone number in Latin digits', function () {
    $matches = $this->detector->detect('Call me at 010 1234 5678 later');

    expect($matches)->toHaveCount(1)
        ->and($matches[0])->toBeInstanceOf(MatchedPattern::class)
        ->and($matches[0]->flagType)->toBe(ChatFlagType::Phone);
});

it('detects Egyptian phone in Eastern Arabic digits', function () {
    $matches = $this->detector->detect('اتصل بي ٠١٠١٢٣٤٥٦٧٨');

    expect($matches)->toHaveCount(1)
        ->and($matches[0]->flagType)->toBe(ChatFlagType::Phone);
});

it('detects spaced and dashed Egyptian phone variants', function () {
    $matches = $this->detector->detect('Phone: 0 1 0 - 1 2 3 4 - 5 6 7 8');

    expect($matches)->toHaveCount(1)
        ->and($matches[0]->flagType)->toBe(ChatFlagType::Phone);
});

it('detects international E.164 phone numbers', function () {
    $matches = $this->detector->detect('Call +201001234567 today');

    expect($matches)->toHaveCount(1)
        ->and($matches[0]->flagType)->toBe(ChatFlagType::Phone);
});

it('detects standard email address', function () {
    $matches = $this->detector->detect('Email me at me+test@example.com');

    expect($matches)->toHaveCount(1)
        ->and($matches[0]->flagType)->toBe(ChatFlagType::Email);
});

it('detects dotted email address', function () {
    $matches = $this->detector->detect('My address is first.last@gmail.com please');

    expect($matches)->toHaveCount(1)
        ->and($matches[0]->flagType)->toBe(ChatFlagType::Email);
});

it('detects external link to t.me', function () {
    $matches = $this->detector->detect('Find me at https://t.me/instaparty for chat');

    expect($matches)->toHaveCount(1)
        ->and($matches[0]->flagType)->toBe(ChatFlagType::ExternalLink);
});

it('detects external link to wa.me', function () {
    $matches = $this->detector->detect('WhatsApp: https://wa.me/01001234567');

    $types = array_map(fn (MatchedPattern $m) => $m->flagType, $matches);

    expect($types)->toContain(ChatFlagType::ExternalLink);
});

it('does not flag internal instaparty.eg link', function () {
    $matches = $this->detector->detect('See our service page at https://instaparty.eg/services/123');

    $types = array_map(fn (MatchedPattern $m) => $m->flagType, $matches);

    expect($types)->not->toContain(ChatFlagType::ExternalLink);
});

it('returns empty array for innocent text', function () {
    $matches = $this->detector->detect('Looking forward to the party!');

    expect($matches)->toBe([]);
});

it('returns three distinct flag types when message contains phone email and link', function () {
    $body = 'Reach me at 01012345678 or test@example.com or https://t.me/me';

    $matches = $this->detector->detect($body);

    expect($matches)->toHaveCount(3);

    $types = array_map(fn (MatchedPattern $m) => $m->flagType, $matches);

    expect($types)->toContain(ChatFlagType::Phone)
        ->and($types)->toContain(ChatFlagType::Email)
        ->and($types)->toContain(ChatFlagType::ExternalLink);

    // Ensure deduplication: at most one MatchedPattern per flag type.
    expect(count(array_unique(array_map(fn (MatchedPattern $m) => $m->flagType->value, $matches))))
        ->toBe(3);
});

it('returns empty array for empty string', function () {
    expect($this->detector->detect(''))->toBe([]);
});

it('returns empty array for whitespace-only string', function () {
    expect($this->detector->detect("   \t\n  "))->toBe([]);
});
