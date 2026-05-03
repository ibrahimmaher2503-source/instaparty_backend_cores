<?php

declare(strict_types=1);

use App\Modules\Reviews\Domain\Models\ReviewModerationLog;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Schema;

it('reviews module application layer does not directly import other modules domain models', function (): void {
    expect('App\\Modules\\Reviews\\Application')
        ->not->toUse('App\\Modules\\Booking\\Domain\\Models')
        ->not->toUse('App\\Modules\\Catalog\\Domain\\Models')
        ->not->toUse('App\\Modules\\Identity\\Domain\\Models');
});

it('reviews module actions do not import other modules infrastructure', function (): void {
    expect('App\\Modules\\Reviews\\Application\\Actions')
        ->not->toUse('App\\Modules\\Booking\\Infrastructure')
        ->not->toUse('App\\Modules\\Catalog\\Infrastructure')
        ->not->toUse('App\\Modules\\Identity\\Infrastructure');
});

it('reviews module listeners do not import other modules infrastructure', function (): void {
    expect('App\\Modules\\Reviews\\Application\\Listeners')
        ->not->toUse('App\\Modules\\Booking\\Infrastructure')
        ->not->toUse('App\\Modules\\Catalog\\Infrastructure')
        ->not->toUse('App\\Modules\\Identity\\Infrastructure');
});

it('ReviewModerationLog model does not use SoftDeletes', function (): void {
    expect(in_array(SoftDeletes::class, class_uses_recursive(ReviewModerationLog::class), true))
        ->toBeFalse('ReviewModerationLog must not use SoftDeletes trait');
});

it('review_moderation_log table has no deleted_at column', function (): void {
    expect(Schema::hasColumn('review_moderation_log', 'deleted_at'))->toBeFalse();
});

it('review_moderation_log table has no updated_at column (true append-only)', function (): void {
    expect(Schema::hasColumn('review_moderation_log', 'updated_at'))->toBeFalse();
});
