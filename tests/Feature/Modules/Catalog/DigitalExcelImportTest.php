<?php

declare(strict_types=1);

use App\Modules\Catalog\Application\Actions\ImportDigitalServicesFromExcelAction;
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
function makeDigitalExcelFile(array $rows): UploadedFile
{
    $headers = [
        'name_en',
        'name_ar',
        'short_description_en',
        'short_description_ar',
        'base_price_minor',
        'category_id',
        'delivery_method',
        'has_expiry',
        'expiry_days_after_purchase',
        'is_refundable_after_delivery',
        'redemption_url_template',
    ];

    $path = tempnam(sys_get_temp_dir(), 'digital_import_').'.csv';
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
        originalName: 'digital.csv',
        mimeType: 'text/csv',
        error: UPLOAD_ERR_OK,
        test: true,
    );
}

function makeApprovedDigitalVendor(): VendorProfile
{
    $user = User::factory()->phoneVerified()->asVendor()->create();
    $vendor = VendorProfile::factory()->approved()->create(['user_id' => $user->id]);

    VendorApprovedProductType::create([
        'vendor_profile_id' => $vendor->id,
        'product_type' => ProductType::Digital,
        'approved_at' => now(),
    ]);

    return $vendor->refresh();
}

function validDigitalRow(int $categoryId): array
{
    return [
        'name_en' => 'E-Invitation EN',
        'name_ar' => 'دعوة إلكترونية AR',
        'short_description_en' => 'Beautiful digital birthday invitation',
        'short_description_ar' => 'دعوة عيد ميلاد رقمية جميلة',
        'base_price_minor' => 5000,
        'category_id' => $categoryId,
        'delivery_method' => 'email',
        'has_expiry' => 0,
        'expiry_days_after_purchase' => '',
        'is_refundable_after_delivery' => 0,
        'redemption_url_template' => '',
    ];
}

// =========================================================================
// Tests
// =========================================================================

it('all valid digital rows complete the import and create services in DB', function () {
    $vendor = makeApprovedDigitalVendor();
    $category = Category::factory()->create(['allowed_product_types' => ['digital']]);

    $file = makeDigitalExcelFile([
        validDigitalRow($category->id),
        array_merge(validDigitalRow($category->id), ['name_en' => 'Gift Link EN', 'name_ar' => 'رابط هدية AR']),
    ]);

    $import = app(ImportDigitalServicesFromExcelAction::class)->execute(
        file: $file,
        vendorProfileId: $vendor->id,
    );

    expect($import->status)->toBe('completed');
    expect($import->imported_rows)->toBe(2);
    expect($import->error_rows)->toBe(0);

    $this->assertDatabaseCount('services', 2);
    $this->assertDatabaseCount('service_digital_details', 2);
})->group('catalog', 'import', 'digital');

it('one invalid digital row fails the entire import with no partial commit', function () {
    $vendor = makeApprovedDigitalVendor();
    $category = Category::factory()->create(['allowed_product_types' => ['digital']]);

    $invalidRow = array_merge(validDigitalRow($category->id), ['name_en' => '']);

    $file = makeDigitalExcelFile([
        validDigitalRow($category->id),
        $invalidRow,
    ]);

    $import = app(ImportDigitalServicesFromExcelAction::class)->execute(
        file: $file,
        vendorProfileId: $vendor->id,
    );

    expect($import->status)->toBe('failed');
    expect($import->imported_rows)->toBe(0);
    expect($import->error_rows)->toBeGreaterThan(0);

    $this->assertDatabaseCount('services', 0);
    $this->assertDatabaseHas('excel_import_errors', ['excel_import_id' => $import->id]);
})->group('catalog', 'import', 'digital');

it('has_expiry=true with missing expiry_days_after_purchase produces a validation error', function () {
    $vendor = makeApprovedDigitalVendor();
    $category = Category::factory()->create(['allowed_product_types' => ['digital']]);

    $row = array_merge(validDigitalRow($category->id), [
        'has_expiry' => 1,
        'expiry_days_after_purchase' => '',
    ]);

    $file = makeDigitalExcelFile([$row]);

    $import = app(ImportDigitalServicesFromExcelAction::class)->execute(
        file: $file,
        vendorProfileId: $vendor->id,
    );

    expect($import->status)->toBe('failed');
    $this->assertDatabaseHas('excel_import_errors', [
        'excel_import_id' => $import->id,
        'field' => 'expiry_days_after_purchase',
    ]);
})->group('catalog', 'import', 'digital');

it('invalid delivery_method value produces a validation error', function () {
    $vendor = makeApprovedDigitalVendor();
    $category = Category::factory()->create(['allowed_product_types' => ['digital']]);

    $row = array_merge(validDigitalRow($category->id), ['delivery_method' => 'fax']);

    $file = makeDigitalExcelFile([$row]);

    $import = app(ImportDigitalServicesFromExcelAction::class)->execute(
        file: $file,
        vendorProfileId: $vendor->id,
    );

    expect($import->status)->toBe('failed');
    $this->assertDatabaseHas('excel_import_errors', [
        'excel_import_id' => $import->id,
        'field' => 'delivery_method',
    ]);
})->group('catalog', 'import', 'digital');

it('digital import error messages have bilingual shape {en, ar}', function () {
    $vendor = makeApprovedDigitalVendor();
    $category = Category::factory()->create(['allowed_product_types' => ['digital']]);

    $badRow = array_merge(validDigitalRow($category->id), [
        'name_en' => '',
        'name_ar' => '',
    ]);

    $file = makeDigitalExcelFile([$badRow]);

    $import = app(ImportDigitalServicesFromExcelAction::class)->execute(
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
})->group('catalog', 'import', 'digital');
