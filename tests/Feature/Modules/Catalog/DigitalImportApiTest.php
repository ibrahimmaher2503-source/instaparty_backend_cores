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

function makeDigitalApiVendor(): array
{
    $user = User::factory()->phoneVerified()->asVendor()->create();
    $vendor = VendorProfile::factory()->approved()->create(['user_id' => $user->id]);

    VendorApprovedProductType::create([
        'vendor_profile_id' => $vendor->id,
        'product_type' => ProductType::Digital,
        'approved_at' => now(),
    ]);

    return ['user' => $user, 'vendor' => $vendor->refresh()];
}

/**
 * @param  array<int, array<string, mixed>>  $rows
 */
function makeDigitalApiCsvFile(array $rows): UploadedFile
{
    $headers = [
        'name_en', 'name_ar',
        'short_description_en', 'short_description_ar',
        'base_price_minor', 'category_id',
        'delivery_method', 'has_expiry',
        'expiry_days_after_purchase', 'is_refundable_after_delivery',
        'redemption_url_template',
    ];

    $path = tempnam(sys_get_temp_dir(), 'digital_api_').'.csv';
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

function validDigitalApiRow(int $categoryId): array
{
    return [
        'name_en' => 'E-Invitation EN',
        'name_ar' => 'دعوة إلكترونية AR',
        'short_description_en' => 'Beautiful digital invitation',
        'short_description_ar' => 'دعوة رقمية جميلة',
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

it('authenticated vendor with valid digital file and own store_id gets 200 completed', function () {
    ['user' => $user, 'vendor' => $vendor] = makeDigitalApiVendor();
    $category = Category::factory()->create(['allowed_product_types' => ['digital']]);
    $file = makeDigitalApiCsvFile([validDigitalApiRow($category->id)]);

    $response = $this->actingAs($user, 'sanctum')->post('/api/v1/vendor/services/digital/import', [
        'store_id' => $vendor->public_id,
        'file' => $file,
    ]);

    $response->assertStatus(200);
    $response->assertJsonPath('data.status', 'completed');
    $response->assertJsonPath('data.imported_rows', 1);
})->group('catalog', 'api', 'digital');

it('digital import with store_id belonging to another vendor returns 403', function () {
    ['user' => $user] = makeDigitalApiVendor();
    $otherVendorProfile = VendorProfile::factory()->approved()->create();
    $category = Category::factory()->create(['allowed_product_types' => ['digital']]);
    $file = makeDigitalApiCsvFile([validDigitalApiRow($category->id)]);

    $response = $this->actingAs($user, 'sanctum')->post('/api/v1/vendor/services/digital/import', [
        'store_id' => $otherVendorProfile->public_id,
        'file' => $file,
    ]);

    $response->assertStatus(403);
})->group('catalog', 'api', 'digital');

it('non-csv/xlsx digital file upload returns 422', function () {
    ['user' => $user, 'vendor' => $vendor] = makeDigitalApiVendor();

    $pdf = UploadedFile::fake()->create('services.pdf', 100, 'application/pdf');

    $response = $this->actingAs($user, 'sanctum')->post('/api/v1/vendor/services/digital/import', [
        'store_id' => $vendor->public_id,
        'file' => $pdf,
    ]);

    $response->assertStatus(422);
})->group('catalog', 'api', 'digital');

it('missing store_id on digital import returns 422', function () {
    ['user' => $user] = makeDigitalApiVendor();
    $category = Category::factory()->create(['allowed_product_types' => ['digital']]);
    $file = makeDigitalApiCsvFile([validDigitalApiRow($category->id)]);

    $response = $this->actingAs($user, 'sanctum')->post('/api/v1/vendor/services/digital/import', [
        'file' => $file,
    ]);

    $response->assertStatus(422);
})->group('catalog', 'api', 'digital');

it('unauthenticated digital import request returns 401', function () {
    $response = $this->post('/api/v1/vendor/services/digital/import', []);

    $response->assertStatus(401);
})->group('catalog', 'api', 'digital');

it('digital file with invalid row returns 422 with status failed and errors array', function () {
    ['user' => $user, 'vendor' => $vendor] = makeDigitalApiVendor();
    $category = Category::factory()->create(['allowed_product_types' => ['digital']]);

    $invalidRow = array_merge(validDigitalApiRow($category->id), ['name_en' => '']);
    $file = makeDigitalApiCsvFile([$invalidRow]);

    $response = $this->actingAs($user, 'sanctum')->post('/api/v1/vendor/services/digital/import', [
        'store_id' => $vendor->public_id,
        'file' => $file,
    ]);

    $response->assertStatus(422);
    $response->assertJsonPath('data.status', 'failed');
    $this->assertDatabaseCount('services', 0);
})->group('catalog', 'api', 'digital');

it('digital file with invalid delivery_method returns 422 with per-row errors', function () {
    ['user' => $user, 'vendor' => $vendor] = makeDigitalApiVendor();
    $category = Category::factory()->create(['allowed_product_types' => ['digital']]);

    $invalidRow = array_merge(validDigitalApiRow($category->id), ['delivery_method' => 'fax']);
    $file = makeDigitalApiCsvFile([$invalidRow]);

    $response = $this->actingAs($user, 'sanctum')->post('/api/v1/vendor/services/digital/import', [
        'store_id' => $vendor->public_id,
        'file' => $file,
    ]);

    $response->assertStatus(422);
    $response->assertJsonPath('data.status', 'failed');
})->group('catalog', 'api', 'digital');

it('submitted vendor_id on digital import is ignored and auth vendor is used', function () {
    ['user' => $user, 'vendor' => $vendor] = makeDigitalApiVendor();

    $otherUser = User::factory()->phoneVerified()->asVendor()->create();
    $otherVendor = VendorProfile::factory()->approved()->create(['user_id' => $otherUser->id]);

    $category = Category::factory()->create(['allowed_product_types' => ['digital']]);
    $file = makeDigitalApiCsvFile([validDigitalApiRow($category->id)]);

    $response = $this->actingAs($user, 'sanctum')->post('/api/v1/vendor/services/digital/import', [
        'store_id' => $vendor->public_id,
        'vendor_id' => $otherVendor->id,
        'file' => $file,
    ]);

    $response->assertStatus(200);

    $this->assertDatabaseHas('services', ['vendor_profile_id' => $vendor->id]);
    $this->assertDatabaseMissing('services', ['vendor_profile_id' => $otherVendor->id]);
})->group('catalog', 'api', 'digital');
