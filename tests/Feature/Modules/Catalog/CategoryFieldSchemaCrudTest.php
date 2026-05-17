<?php

declare(strict_types=1);

use App\Modules\Catalog\Application\Actions\CreateCategoryAction;
use App\Modules\Catalog\Application\Actions\CreateCategoryFieldSchemaAction;
use App\Modules\Catalog\Application\Actions\DeleteCategoryFieldSchemaAction;
use App\Modules\Catalog\Application\Actions\UpdateCategoryFieldSchemaAction;
use App\Modules\Catalog\Application\DTOs\CategoryDTO;
use App\Modules\Catalog\Application\DTOs\CategoryFieldSchemaDTO;
use App\Modules\Catalog\Domain\Enums\ProductType;
use App\Modules\Catalog\Domain\Models\CategoryFieldSchema;
use App\Modules\Identity\Domain\Models\User;
use Database\Seeders\IdentityRolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(IdentityRolesSeeder::class);
    $this->admin = User::factory()->phoneVerified()->superAdmin()->create();
    $this->category = app(CreateCategoryAction::class)->execute(new CategoryDTO(
        code: 'test-cfs-cat',
        name: ['en' => 'Inflatables', 'ar' => 'النفخ'],
        allowedProductTypes: [
            ProductType::Rental->value,
            ProductType::Sale->value,
            ProductType::Digital->value,
        ],
    ), $this->admin);
});

it('creates schema for rental type', function (): void {
    $dto = new CategoryFieldSchemaDTO(
        categoryId: $this->category->id,
        productType: ProductType::Rental,
        fieldKey: 'requires_electricity',
        fieldLabel: ['en' => 'Requires electricity', 'ar' => 'يحتاج كهرباء'],
        fieldType: 'boolean',
        isRequired: true,
        sortOrder: 1,
    );

    $schema = app(CreateCategoryFieldSchemaAction::class)->execute($dto, $this->admin);

    expect($schema->product_type)->toBe(ProductType::Rental);
    expect($schema->field_key)->toBe('requires_electricity');
    expect($schema->getTranslation('field_label', 'ar'))->toBe('يحتاج كهرباء');
    expect(DB::table('audit_logs')->where('action', 'category_field_schema_created')->count())->toBe(1);
})->group('catalog', 'taxonomy', 'schemas', 'rental');

it('creates schema for sale type', function (): void {
    $dto = new CategoryFieldSchemaDTO(
        categoryId: $this->category->id,
        productType: ProductType::Sale,
        fieldKey: 'allergens',
        fieldLabel: ['en' => 'Allergens', 'ar' => 'مسببات الحساسية'],
        fieldType: 'multiselect',
        options: ['nuts', 'dairy', 'gluten'],
    );

    $schema = app(CreateCategoryFieldSchemaAction::class)->execute($dto, $this->admin);

    expect($schema->product_type)->toBe(ProductType::Sale);
    expect($schema->options)->toBe(['nuts', 'dairy', 'gluten']);
})->group('catalog', 'taxonomy', 'schemas', 'sale');

it('creates schema for digital type', function (): void {
    $dto = new CategoryFieldSchemaDTO(
        categoryId: $this->category->id,
        productType: ProductType::Digital,
        fieldKey: 'template_style',
        fieldLabel: ['en' => 'Template style', 'ar' => 'نمط القالب'],
        fieldType: 'select',
        options: ['classic', 'modern', 'minimalist'],
    );

    $schema = app(CreateCategoryFieldSchemaAction::class)->execute($dto, $this->admin);

    expect($schema->product_type)->toBe(ProductType::Digital);
})->group('catalog', 'taxonomy', 'schemas', 'digital');

it('enforces UNIQUE (category_id, product_type, field_key)', function (): void {
    $dto = new CategoryFieldSchemaDTO(
        categoryId: $this->category->id,
        productType: ProductType::Rental,
        fieldKey: 'dup_key',
        fieldLabel: ['en' => 'Dup', 'ar' => 'مكرر'],
        fieldType: 'text',
    );

    app(CreateCategoryFieldSchemaAction::class)->execute($dto, $this->admin);

    expect(fn () => app(CreateCategoryFieldSchemaAction::class)->execute($dto, $this->admin))
        ->toThrow(\Illuminate\Database\QueryException::class);
})->group('catalog', 'taxonomy', 'schemas');

it('allows same field_key across different product types', function (): void {
    foreach ([ProductType::Rental, ProductType::Sale, ProductType::Digital] as $type) {
        app(CreateCategoryFieldSchemaAction::class)->execute(new CategoryFieldSchemaDTO(
            categoryId: $this->category->id,
            productType: $type,
            fieldKey: 'shared_key',
            fieldLabel: ['en' => 'Shared', 'ar' => 'مشترك'],
            fieldType: 'text',
        ), $this->admin);
    }

    expect(CategoryFieldSchema::query()->where('field_key', 'shared_key')->count())->toBe(3);
})->group('catalog', 'taxonomy', 'schemas');

it('updates a schema', function (): void {
    $schema = app(CreateCategoryFieldSchemaAction::class)->execute(new CategoryFieldSchemaDTO(
        categoryId: $this->category->id,
        productType: ProductType::Rental,
        fieldKey: 'will_update',
        fieldLabel: ['en' => 'Original', 'ar' => 'أصلي'],
        fieldType: 'text',
        isRequired: false,
    ), $this->admin);

    $updated = app(UpdateCategoryFieldSchemaAction::class)->execute(
        $schema,
        new CategoryFieldSchemaDTO(
            categoryId: $this->category->id,
            productType: ProductType::Rental,
            fieldKey: 'will_update',
            fieldLabel: ['en' => 'Renamed', 'ar' => 'معدل'],
            fieldType: 'text',
            isRequired: true,
        ),
        $this->admin,
    );

    expect($updated->is_required)->toBeTrue();
    expect($updated->getTranslation('field_label', 'en'))->toBe('Renamed');
})->group('catalog', 'taxonomy', 'schemas');

it('deletes a schema', function (): void {
    $schema = app(CreateCategoryFieldSchemaAction::class)->execute(new CategoryFieldSchemaDTO(
        categoryId: $this->category->id,
        productType: ProductType::Rental,
        fieldKey: 'will_delete',
        fieldLabel: ['en' => 'D', 'ar' => 'م'],
        fieldType: 'text',
    ), $this->admin);

    app(DeleteCategoryFieldSchemaAction::class)->execute($schema, $this->admin);

    expect(CategoryFieldSchema::query()->find($schema->id))->toBeNull();
    expect(DB::table('audit_logs')->where('action', 'category_field_schema_deleted')->count())->toBe(1);
})->group('catalog', 'taxonomy', 'schemas');
