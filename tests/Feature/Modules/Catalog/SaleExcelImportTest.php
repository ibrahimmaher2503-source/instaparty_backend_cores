<?php

declare(strict_types=1);

use App\Modules\Catalog\Application\Actions\ImportSaleServicesFromExcelAction;
use App\Modules\Catalog\Domain\Enums\ProductType;
use App\Modules\Catalog\Domain\Models\Category;
use App\Modules\Catalog\Domain\Models\ExcelImportError;
use App\Modules\Geography\Database\Seeders\EgyptGeographySeeder;
use App\Modules\Identity\Domain\Models\User;
use App\Modules\Identity\Domain\Models\VendorApprovedProductType;
use App\Modules\Identity\Domain\Models\VendorProfile;
use Database\Seeders\IdentityRolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(IdentityRolesSeeder::class);
    $this->seed(EgyptGeographySeeder::class);
});

// =========================================================================
// Helpers
// =========================================================================

/**
 * @param  array<int, array<string, mixed>>  $rows
 */
function makeSaleExcelFile(array $rows): UploadedFile
{
    $headers = [
        'name_en',
        'name_ar',
        'short_description_en',
        'short_description_ar',
        'base_price_minor',
        'category_id',
        'is_perishable',
        'is_made_to_order',
        'lead_time_hours',
        'stock_quantity',
    ];

    $path = tempnam(sys_get_temp_dir(), 'sale_import_').'.csv';
    $fp = fopen($path, 'w');
    fputcsv($fp, $headers);

    foreach ($rows as $row) {
        $line = [];
        foreach ($headers as $col) {
            $line[] = $row[$col] ?? '';
        }
        fputcsv($fp, $line);
    }

    fclose($fp);

    return new UploadedFile(
        path: $path,
        originalName: 'sales.csv',
        mimeType: 'text/csv',
        error: UPLOAD_ERR_OK,
        test: true,
    );
}

function makeApprovedSaleVendor(): VendorProfile
{
    $user = User::factory()->phoneVerified()->asVendor()->create();
    $vendor = VendorProfile::factory()->approved()->create(['user_id' => $user->id]);

    VendorApprovedProductType::create([
        'vendor_profile_id' => $vendor->id,
        'product_type' => ProductType::Sale,
        'approved_at' => now(),
    ]);

    return $vendor->refresh();
}

function validSaleRow(int $categoryId): array
{
    return [
        'name_en' => 'Birthday Cake EN',
        'name_ar' => 'كيكة عيد ميلاد AR',
        'short_description_en' => 'Delicious custom birthday cake',
        'short_description_ar' => 'كيكة عيد ميلاد مخصصة',
        'base_price_minor' => 15000,
        'category_id' => $categoryId,
        'is_perishable' => 1,
        'is_made_to_order' => 0,
        'lead_time_hours' => '',
        'stock_quantity' => 10,
    ];
}

// =========================================================================
// Tests
// =========================================================================

it('all valid sale rows complete the import and create services in DB', function () {
    $vendor = makeApprovedSaleVendor();
    $category = Category::factory()->create(['allowed_product_types' => ['sale']]);

    $file = makeSaleExcelFile([
        validSaleRow($category->id),
        array_merge(validSaleRow($category->id), ['name_en' => 'Gift Hamper EN', 'name_ar' => 'سلة هدايا AR']),
    ]);

    $import = app(ImportSaleServicesFromExcelAction::class)->execute(
        file: $file,
        vendorProfileId: $vendor->id,
    );

    expect($import->status)->toBe('completed');
    expect($import->imported_rows)->toBe(2);
    expect($import->error_rows)->toBe(0);

    $this->assertDatabaseCount('services', 2);
    $this->assertDatabaseCount('service_sale_details', 2);
})->group('catalog', 'import', 'sale');

it('one invalid sale row fails the entire import with no partial commit', function () {
    $vendor = makeApprovedSaleVendor();
    $category = Category::factory()->create(['allowed_product_types' => ['sale']]);

    $invalidRow = array_merge(validSaleRow($category->id), ['name_en' => '']);

    $file = makeSaleExcelFile([
        validSaleRow($category->id),
        $invalidRow,
    ]);

    $import = app(ImportSaleServicesFromExcelAction::class)->execute(
        file: $file,
        vendorProfileId: $vendor->id,
    );

    expect($import->status)->toBe('failed');
    expect($import->imported_rows)->toBe(0);
    expect($import->error_rows)->toBeGreaterThan(0);

    $this->assertDatabaseCount('services', 0);
    $this->assertDatabaseHas('excel_import_errors', ['excel_import_id' => $import->id]);
})->group('catalog', 'import', 'sale');

it('is_made_to_order=true with missing lead_time_hours produces a validation error', function () {
    $vendor = makeApprovedSaleVendor();
    $category = Category::factory()->create(['allowed_product_types' => ['sale']]);

    $row = array_merge(validSaleRow($category->id), [
        'is_made_to_order' => 1,
        'lead_time_hours' => '',
    ]);

    $file = makeSaleExcelFile([$row]);

    $import = app(ImportSaleServicesFromExcelAction::class)->execute(
        file: $file,
        vendorProfileId: $vendor->id,
    );

    expect($import->status)->toBe('failed');
    $this->assertDatabaseHas('excel_import_errors', [
        'excel_import_id' => $import->id,
        'field' => 'lead_time_hours',
    ]);
})->group('catalog', 'import', 'sale');

it('sale import error messages in excel_import_errors have bilingual shape {en, ar}', function () {
    $vendor = makeApprovedSaleVendor();
    $category = Category::factory()->create(['allowed_product_types' => ['sale']]);

    $badRow = array_merge(validSaleRow($category->id), [
        'name_en' => '',
        'name_ar' => '',
    ]);

    $file = makeSaleExcelFile([$badRow]);

    $import = app(ImportSaleServicesFromExcelAction::class)->execute(
        file: $file,
        vendorProfileId: $vendor->id,
    );

    expect($import->status)->toBe('failed');

    $errors = ExcelImportError::where('excel_import_id', $import->id)->get();

    expect($errors)->not->toBeEmpty();

    foreach ($errors as $error) {
        $message = $error->message;
        expect($message)->toBeArray();
        expect($message)->toHaveKeys(['en', 'ar']);
        expect($message['en'])->toBeString()->not->toBeEmpty();
        expect($message['ar'])->toBeString()->not->toBeEmpty();
    }
})->group('catalog', 'import', 'sale');
