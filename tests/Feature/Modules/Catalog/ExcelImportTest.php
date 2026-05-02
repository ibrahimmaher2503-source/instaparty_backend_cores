<?php

declare(strict_types=1);

use App\Modules\Catalog\Application\Actions\ImportRentalServicesFromExcelAction;
use App\Modules\Catalog\Domain\Models\Category;
use App\Modules\Catalog\Domain\Models\ExcelImportError;
use App\Modules\Geography\Database\Seeders\EgyptGeographySeeder;
use App\Modules\Identity\Domain\Models\User;
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
 * Creates a temporary CSV file that the RentalServicesImport (WithHeadingRow)
 * can parse. Using CSV because the Maatwebsite\Excel package supports it
 * without extra dependencies, and the importer uses ToCollection + WithHeadingRow.
 *
 * @param  array<int, array<string, mixed>>  $rows
 */
function makeRentalExcelFile(array $rows): UploadedFile
{
    $headers = [
        'name_en',
        'name_ar',
        'short_description_en',
        'short_description_ar',
        'base_price_minor',
        'requires_electricity',
        'requires_outdoor_space',
        'default_rental_duration_hours',
        'setup_time_minutes',
        'teardown_time_minutes',
        'security_deposit_minor',
        'minimum_space_sqm',
        'category_id',
    ];

    $path = tempnam(sys_get_temp_dir(), 'rental_import_').'.csv';

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
        originalName: 'rentals.csv',
        mimeType: 'text/csv',
        error: UPLOAD_ERR_OK,
        test: true,
    );
}

function makeApprovedVendor(): VendorProfile
{
    $user = User::factory()->phoneVerified()->asVendor()->create();

    return VendorProfile::factory()->approved()->create(['user_id' => $user->id]);
}

function validRow(int $categoryId): array
{
    return [
        'name_en' => 'Test Inflatable EN',
        'name_ar' => 'نفخة اختبار AR',
        'short_description_en' => 'Short description EN',
        'short_description_ar' => 'وصف قصير AR',
        'base_price_minor' => 150000,
        'requires_electricity' => 0,
        'requires_outdoor_space' => 0,
        'default_rental_duration_hours' => 4,
        'setup_time_minutes' => 60,
        'teardown_time_minutes' => 30,
        'security_deposit_minor' => 5000,
        'minimum_space_sqm' => 25,
        'category_id' => $categoryId,
    ];
}

// =========================================================================
// Tests
// =========================================================================

it('all valid rows complete the import and create services in DB', function () {
    $vendor = makeApprovedVendor();
    $category = Category::factory()->create(['allowed_product_types' => ['rental']]);

    $file = makeRentalExcelFile([
        validRow($category->id),
        array_merge(validRow($category->id), ['name_en' => 'Second Service EN', 'name_ar' => 'ثاني AR']),
    ]);

    $import = app(ImportRentalServicesFromExcelAction::class)->execute(
        file: $file,
        vendorProfileId: $vendor->id,
    );

    expect($import->status)->toBe('completed');
    expect($import->imported_rows)->toBe(2);
    expect($import->error_rows)->toBe(0);

    $this->assertDatabaseCount('services', 2);
    $this->assertDatabaseCount('service_rental_details', 2);
})->group('catalog', 'import');

it('one invalid row among valid rows fails the entire import (no partial commit)', function () {
    $vendor = makeApprovedVendor();
    $category = Category::factory()->create(['allowed_product_types' => ['rental']]);

    // Row 1 is valid; row 2 is missing the required name_en field
    $invalidRow = array_merge(validRow($category->id), ['name_en' => '']);

    $file = makeRentalExcelFile([
        validRow($category->id),
        $invalidRow,
    ]);

    $import = app(ImportRentalServicesFromExcelAction::class)->execute(
        file: $file,
        vendorProfileId: $vendor->id,
    );

    expect($import->status)->toBe('failed');
    expect($import->imported_rows)->toBe(0);
    expect($import->error_rows)->toBeGreaterThan(0);

    // Zero services persisted
    $this->assertDatabaseCount('services', 0);
    // Errors were persisted
    $this->assertDatabaseHas('excel_import_errors', ['excel_import_id' => $import->id]);
})->group('catalog', 'import');

it('error messages in excel_import_errors have bilingual shape {en, ar}', function () {
    $vendor = makeApprovedVendor();
    $category = Category::factory()->create(['allowed_product_types' => ['rental']]);

    // Row with multiple missing required fields
    $badRow = array_merge(validRow($category->id), [
        'name_en' => '',
        'name_ar' => '',
    ]);

    $file = makeRentalExcelFile([$badRow]);

    $import = app(ImportRentalServicesFromExcelAction::class)->execute(
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
})->group('catalog', 'import');
