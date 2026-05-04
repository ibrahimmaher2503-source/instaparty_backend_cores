<?php

declare(strict_types=1);

use App\Modules\Catalog\Domain\Enums\ProductType;
use App\Modules\Catalog\Domain\Models\Category;
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
    $this->withHeader('Accept', 'application/json');
});

// =========================================================================
// Helpers
// =========================================================================

function makeSaleApiVendor(): array
{
    $user = User::factory()->phoneVerified()->asVendor()->create();
    $vendor = VendorProfile::factory()->approved()->create(['user_id' => $user->id]);

    VendorApprovedProductType::create([
        'vendor_profile_id' => $vendor->id,
        'product_type' => ProductType::Sale,
        'approved_at' => now(),
    ]);

    return ['user' => $user, 'vendor' => $vendor->refresh()];
}

/**
 * @param  array<int, array<string, mixed>>  $rows
 */
function makeSaleApiCsvFile(array $rows): UploadedFile
{
    $headers = [
        'name_en', 'name_ar',
        'short_description_en', 'short_description_ar',
        'base_price_minor', 'category_id',
        'is_perishable', 'is_made_to_order',
        'lead_time_hours', 'stock_quantity',
    ];

    $path = tempnam(sys_get_temp_dir(), 'sale_api_').'.csv';
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

function validSaleApiRow(int $categoryId): array
{
    return [
        'name_en' => 'Cake EN',
        'name_ar' => 'كيكة AR',
        'short_description_en' => 'A delicious cake',
        'short_description_ar' => 'كيكة لذيذة',
        'base_price_minor' => 20000,
        'category_id' => $categoryId,
        'is_perishable' => 1,
        'is_made_to_order' => 0,
        'lead_time_hours' => '',
        'stock_quantity' => '',
    ];
}

// =========================================================================
// Tests
// =========================================================================

it('authenticated vendor with valid file and own store_id gets 200 completed', function () {
    ['user' => $user, 'vendor' => $vendor] = makeSaleApiVendor();
    $category = Category::factory()->create(['allowed_product_types' => ['sale']]);
    $file = makeSaleApiCsvFile([validSaleApiRow($category->id)]);

    $response = $this->actingAs($user, 'sanctum')->post('/api/v1/vendor/services/sale/import', [
        'store_id' => $vendor->public_id,
        'file' => $file,
    ]);

    $response->assertStatus(200);
    $response->assertJsonPath('data.status', 'completed');
    $response->assertJsonPath('data.imported_rows', 1);
})->group('catalog', 'api', 'sale');

it('valid file with store_id belonging to another vendor returns 403 store_not_owned', function () {
    ['user' => $user] = makeSaleApiVendor();
    $otherVendorProfile = VendorProfile::factory()->approved()->create();
    $category = Category::factory()->create(['allowed_product_types' => ['sale']]);
    $file = makeSaleApiCsvFile([validSaleApiRow($category->id)]);

    $response = $this->actingAs($user, 'sanctum')->post('/api/v1/vendor/services/sale/import', [
        'store_id' => $otherVendorProfile->public_id,
        'file' => $file,
    ]);

    $response->assertStatus(403);
})->group('catalog', 'api', 'sale');

it('non-csv/xlsx file upload returns 422', function () {
    ['user' => $user, 'vendor' => $vendor] = makeSaleApiVendor();

    $pdf = UploadedFile::fake()->create('services.pdf', 100, 'application/pdf');

    $response = $this->actingAs($user, 'sanctum')->post('/api/v1/vendor/services/sale/import', [
        'store_id' => $vendor->public_id,
        'file' => $pdf,
    ]);

    $response->assertStatus(422);
})->group('catalog', 'api', 'sale');

it('missing store_id returns 422', function () {
    ['user' => $user] = makeSaleApiVendor();
    $category = Category::factory()->create(['allowed_product_types' => ['sale']]);
    $file = makeSaleApiCsvFile([validSaleApiRow($category->id)]);

    $response = $this->actingAs($user, 'sanctum')->post('/api/v1/vendor/services/sale/import', [
        'file' => $file,
    ]);

    $response->assertStatus(422);
})->group('catalog', 'api', 'sale');

it('unauthenticated sale import request returns 401', function () {
    $response = $this->post('/api/v1/vendor/services/sale/import', []);

    $response->assertStatus(401);
})->group('catalog', 'api', 'sale');

it('file with an invalid row returns 422 with status failed and errors array', function () {
    ['user' => $user, 'vendor' => $vendor] = makeSaleApiVendor();
    $category = Category::factory()->create(['allowed_product_types' => ['sale']]);

    $invalidRow = array_merge(validSaleApiRow($category->id), ['name_en' => '']);
    $file = makeSaleApiCsvFile([$invalidRow]);

    $response = $this->actingAs($user, 'sanctum')->post('/api/v1/vendor/services/sale/import', [
        'store_id' => $vendor->public_id,
        'file' => $file,
    ]);

    $response->assertStatus(422);
    $response->assertJsonPath('data.status', 'failed');
    $this->assertDatabaseCount('services', 0);
})->group('catalog', 'api', 'sale');

it('submitted vendor_id body param is ignored and auth vendor is used', function () {
    ['user' => $user, 'vendor' => $vendor] = makeSaleApiVendor();

    // Create a second vendor that the attacker claims to be
    $otherUser = User::factory()->phoneVerified()->asVendor()->create();
    $otherVendor = VendorProfile::factory()->approved()->create(['user_id' => $otherUser->id]);

    $category = Category::factory()->create(['allowed_product_types' => ['sale']]);
    $file = makeSaleApiCsvFile([validSaleApiRow($category->id)]);

    $response = $this->actingAs($user, 'sanctum')->post('/api/v1/vendor/services/sale/import', [
        'store_id' => $vendor->public_id,
        'vendor_id' => $otherVendor->id, // should be ignored
        'file' => $file,
    ]);

    $response->assertStatus(200);

    // Service must belong to the authenticated vendor, not the submitted vendor_id
    $this->assertDatabaseHas('services', ['vendor_profile_id' => $vendor->id]);
    $this->assertDatabaseMissing('services', ['vendor_profile_id' => $otherVendor->id]);
})->group('catalog', 'api', 'sale');
