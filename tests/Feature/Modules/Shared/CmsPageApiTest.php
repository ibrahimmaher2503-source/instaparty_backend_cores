<?php

declare(strict_types=1);

use App\Modules\Shared\Application\Actions\PublishCmsPageAction;
use App\Modules\Shared\Application\Actions\UnpublishCmsPageAction;
use App\Modules\Shared\Domain\Enums\CmsSlug;
use App\Modules\Shared\Domain\Models\CmsPage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

use function Pest\Laravel\getJson;

beforeEach(function (): void {
    CmsPage::query()->delete();
});

function makePage(CmsSlug $slug, bool $published = false): CmsPage
{
    return CmsPage::create([
        'public_id' => (string) Str::ulid(),
        'slug' => $slug->value,
        'title' => ['en' => 'Title EN', 'ar' => 'عنوان'],
        'body' => ['en' => '<p>Body EN</p>', 'ar' => '<p>نص</p>'],
        'meta_description' => ['en' => 'Meta EN', 'ar' => 'ميتا'],
        'is_published' => $published,
        'published_at' => $published ? now() : null,
    ]);
}

it('returns a published CMS page in English', function (): void {
    makePage(CmsSlug::Terms, published: true);

    getJson('/api/v1/cms/pages/terms', ['Accept-Language' => 'en'])
        ->assertOk()
        ->assertJsonPath('data.slug', 'terms')
        ->assertJsonPath('data.title', 'Title EN')
        ->assertJsonPath('data.body', '<p>Body EN</p>');
})->group('cms');

it('returns a published CMS page in Arabic', function (): void {
    makePage(CmsSlug::Privacy, published: true);

    getJson('/api/v1/cms/pages/privacy', ['Accept-Language' => 'ar'])
        ->assertOk()
        ->assertJsonPath('data.slug', 'privacy')
        ->assertJsonPath('data.title', 'عنوان')
        ->assertJsonPath('data.body', '<p>نص</p>');
})->group('cms');

it('returns 404 for an unpublished page', function (): void {
    makePage(CmsSlug::About, published: false);

    getJson('/api/v1/cms/pages/about')
        ->assertNotFound();
})->group('cms');

it('returns 404 for an unknown slug', function (): void {
    getJson('/api/v1/cms/pages/unknown-slug')
        ->assertNotFound();
})->group('cms');

it('returns 404 after page is unpublished', function (): void {
    $page = makePage(CmsSlug::Contact, published: true);

    getJson('/api/v1/cms/pages/contact')->assertOk();

    $page->is_published = false;
    $page->save();

    getJson('/api/v1/cms/pages/contact')->assertNotFound();
})->group('cms');

it('publish action sets is_published and published_at', function (): void {
    $page = makePage(CmsSlug::Terms, published: false);

    app(PublishCmsPageAction::class)->execute($page);

    expect($page->fresh())
        ->is_published->toBeTrue()
        ->published_at->not->toBeNull();
})->group('cms');

it('unpublish action clears is_published', function (): void {
    $page = makePage(CmsSlug::Terms, published: true);

    app(UnpublishCmsPageAction::class)->execute($page);

    expect($page->fresh())->is_published->toBeFalse();
})->group('cms');

it('publish action throws when EN body is blank', function (): void {
    $page = CmsPage::create([
        'public_id' => (string) Str::ulid(),
        'slug' => CmsSlug::Terms->value,
        'title' => ['en' => 'T', 'ar' => 'ت'],
        'body' => ['en' => '', 'ar' => '<p>نص</p>'],
        'is_published' => false,
    ]);

    expect(fn () => app(PublishCmsPageAction::class)->execute($page))
        ->toThrow(ValidationException::class);
})->group('cms');
