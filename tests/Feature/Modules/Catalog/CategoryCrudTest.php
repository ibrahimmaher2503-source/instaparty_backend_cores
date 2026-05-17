<?php

declare(strict_types=1);

use App\Modules\Catalog\Application\Actions\CreateCategoryAction;
use App\Modules\Catalog\Application\Actions\DeleteCategoryAction;
use App\Modules\Catalog\Application\Actions\ReorderCategoriesAction;
use App\Modules\Catalog\Application\Actions\UpdateCategoryAction;
use App\Modules\Catalog\Application\DTOs\CategoryDTO;
use App\Modules\Catalog\Domain\Enums\ProductType;
use App\Modules\Catalog\Domain\Models\Category;
use App\Modules\Identity\Domain\Models\User;
use Database\Seeders\IdentityRolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(IdentityRolesSeeder::class);
    $this->admin = User::factory()->phoneVerified()->superAdmin()->create();
});

it('creates a root category with allowed_product_types JSON', function (): void {
    $dto = new CategoryDTO(
        code: 'test-root-cat',
        name: ['en' => 'Test Root', 'ar' => 'جذر تجريبي'],
        allowedProductTypes: [ProductType::Rental->value, ProductType::Sale->value],
    );

    $cat = app(CreateCategoryAction::class)->execute($dto, $this->admin);

    expect($cat->parent_id)->toBeNull();
    expect($cat->allowed_product_types)->toBe([ProductType::Rental->value, ProductType::Sale->value]);
    expect($cat->getTranslation('name', 'ar'))->toBe('جذر تجريبي');
    expect(DB::table('audit_logs')->where('action', 'category_created')->count())->toBe(1);
})->group('catalog', 'taxonomy', 'categories');

it('creates a child category that loads via parent->children', function (): void {
    $parent = app(CreateCategoryAction::class)->execute(new CategoryDTO(
        code: 'test-parent',
        name: ['en' => 'Parent', 'ar' => 'أب'],
        allowedProductTypes: [ProductType::Rental->value],
    ), $this->admin);

    $child = app(CreateCategoryAction::class)->execute(new CategoryDTO(
        code: 'test-child',
        name: ['en' => 'Child', 'ar' => 'ابن'],
        allowedProductTypes: [ProductType::Rental->value],
        parentId: $parent->id,
    ), $this->admin);

    expect($parent->children()->pluck('id')->all())->toContain($child->id);
    expect($child->parent->id)->toBe($parent->id);
})->group('catalog', 'taxonomy', 'categories');

it('updates a category and writes audit', function (): void {
    $cat = app(CreateCategoryAction::class)->execute(new CategoryDTO(
        code: 'test-update',
        name: ['en' => 'Original', 'ar' => 'أصلي'],
        allowedProductTypes: [ProductType::Rental->value],
    ), $this->admin);

    $updated = app(UpdateCategoryAction::class)->execute(
        $cat,
        new CategoryDTO(
            code: 'test-update',
            name: ['en' => 'Renamed', 'ar' => 'معدل'],
            allowedProductTypes: [ProductType::Rental->value, ProductType::Digital->value],
        ),
        $this->admin,
    );

    expect($updated->getTranslation('name', 'en'))->toBe('Renamed');
    expect($updated->allowed_product_types)->toContain(ProductType::Digital->value);
    expect(DB::table('audit_logs')->where('action', 'category_updated')->count())->toBe(1);
})->group('catalog', 'taxonomy', 'categories');

it('refuses to delete a category that has children', function (): void {
    $parent = app(CreateCategoryAction::class)->execute(new CategoryDTO(
        code: 'test-with-kids',
        name: ['en' => 'P', 'ar' => 'ب'],
        allowedProductTypes: [ProductType::Rental->value],
    ), $this->admin);

    app(CreateCategoryAction::class)->execute(new CategoryDTO(
        code: 'test-kid',
        name: ['en' => 'C', 'ar' => 'ا'],
        allowedProductTypes: [ProductType::Rental->value],
        parentId: $parent->id,
    ), $this->admin);

    expect(fn () => app(DeleteCategoryAction::class)->execute($parent, $this->admin))
        ->toThrow(ValidationException::class);

    expect(Category::query()->find($parent->id))->not->toBeNull();
})->group('catalog', 'taxonomy', 'categories');

it('soft-deletes a leaf category', function (): void {
    $leaf = app(CreateCategoryAction::class)->execute(new CategoryDTO(
        code: 'test-leaf',
        name: ['en' => 'Leaf', 'ar' => 'ورقة'],
        allowedProductTypes: [ProductType::Sale->value],
    ), $this->admin);

    app(DeleteCategoryAction::class)->execute($leaf, $this->admin);

    expect(Category::query()->find($leaf->id))->toBeNull();
    expect(Category::withTrashed()->find($leaf->id))->not->toBeNull();
})->group('catalog', 'taxonomy', 'categories');

it('reorders sibling categories atomically', function (): void {
    $a = app(CreateCategoryAction::class)->execute(new CategoryDTO(
        code: 'test-reord-a',
        name: ['en' => 'A', 'ar' => 'أ'],
        allowedProductTypes: [ProductType::Rental->value],
        sortOrder: 0,
    ), $this->admin);
    $b = app(CreateCategoryAction::class)->execute(new CategoryDTO(
        code: 'test-reord-b',
        name: ['en' => 'B', 'ar' => 'ب'],
        allowedProductTypes: [ProductType::Rental->value],
        sortOrder: 1,
    ), $this->admin);
    $c = app(CreateCategoryAction::class)->execute(new CategoryDTO(
        code: 'test-reord-c',
        name: ['en' => 'C', 'ar' => 'ج'],
        allowedProductTypes: [ProductType::Rental->value],
        sortOrder: 2,
    ), $this->admin);

    // Re-order to C, A, B
    app(ReorderCategoriesAction::class)->execute(
        [$c->public_id, $a->public_id, $b->public_id],
        parentId: null,
        actor: $this->admin,
    );

    expect($c->refresh()->sort_order)->toBe(0);
    expect($a->refresh()->sort_order)->toBe(1);
    expect($b->refresh()->sort_order)->toBe(2);
    expect(DB::table('audit_logs')->where('action', 'categories_reordered')->count())->toBe(1);
})->group('catalog', 'taxonomy', 'categories');

it('reorder rejects categories with mismatched parents', function (): void {
    $parent = app(CreateCategoryAction::class)->execute(new CategoryDTO(
        code: 'test-mix-parent',
        name: ['en' => 'P', 'ar' => 'ب'],
        allowedProductTypes: [ProductType::Rental->value],
    ), $this->admin);

    $root = app(CreateCategoryAction::class)->execute(new CategoryDTO(
        code: 'test-mix-root',
        name: ['en' => 'R', 'ar' => 'ر'],
        allowedProductTypes: [ProductType::Rental->value],
    ), $this->admin);

    $child = app(CreateCategoryAction::class)->execute(new CategoryDTO(
        code: 'test-mix-child',
        name: ['en' => 'C', 'ar' => 'ج'],
        allowedProductTypes: [ProductType::Rental->value],
        parentId: $parent->id,
    ), $this->admin);

    expect(fn () => app(ReorderCategoriesAction::class)->execute(
        [$root->public_id, $child->public_id],
        parentId: null,
    ))->toThrow(ValidationException::class);
})->group('catalog', 'taxonomy', 'categories');
