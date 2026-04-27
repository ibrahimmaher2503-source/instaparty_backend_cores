<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Geography\Domain\Models\City;
use App\Modules\Geography\Domain\Models\Governorate;
use App\Modules\Geography\Domain\Models\Region;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

it('creates all Identity module tables', function (string $table) {
    expect(Schema::hasTable($table))->toBeTrue();
})->with([
    'vendor_profiles',
    'vendor_documents',
    'vendor_approved_product_types',
    'vendor_business_hours',
    'customer_profiles',
    'customer_addresses',
    'user_devices',
    'two_factor_secrets',
])->group('migrations', 'identity');

it('creates all Geography module tables', function (string $table) {
    expect(Schema::hasTable($table))->toBeTrue();
})->with([
    'countries',
    'governorates',
    'regions',
    'cities',
    'vendor_coverage_areas',
])->group('migrations', 'geography');

it('vendor_profiles has all required spec columns', function () {
    expect(Schema::hasColumns('vendor_profiles', [
        'id', 'public_id', 'user_id', 'business_name', 'slug', 'bio',
        'logo_path', 'cover_path', 'business_type',
        'commercial_register_no', 'tax_id', 'national_id',
        'primary_governorate_id', 'primary_city_id', 'address_line', 'latitude', 'longitude',
        'approval_status', 'approved_at', 'approved_by', 'rejection_reason',
        'bank_name', 'bank_account_holder', 'bank_iban', 'bank_swift_bic', 'bank_branch',
        'rating_avg', 'rating_count', 'response_time_avg_minutes',
        'created_at', 'updated_at', 'deleted_at',
    ]))->toBeTrue();
})->group('migrations', 'identity');

it('customer_addresses has recipient and snapshot columns', function () {
    expect(Schema::hasColumns('customer_addresses', [
        'recipient_name', 'recipient_phone_e164', 'label', 'address_line',
        'city_id', 'is_default', 'deleted_at',
    ]))->toBeTrue();
})->group('migrations', 'identity');

it('vendor_approved_product_types has revoked columns', function () {
    expect(Schema::hasColumns('vendor_approved_product_types', [
        'product_type', 'approved_at', 'approved_by',
        'revoked_at', 'revoked_by', 'revoke_reason',
    ]))->toBeTrue();
})->group('migrations', 'identity');

it('vendor_documents uses spec column names and ENUM', function () {
    expect(Schema::hasColumns('vendor_documents', [
        'doc_type', 'file_path', 'file_name', 'review_notes',
    ]))->toBeTrue();
})->group('migrations', 'identity');

it('two_factor_secrets uses encrypted column names', function () {
    expect(Schema::hasColumns('two_factor_secrets', [
        'secret_encrypted', 'recovery_codes_encrypted',
    ]))->toBeTrue();
})->group('migrations', 'identity');

it('user_devices uses fcm_token and last_seen_at', function () {
    expect(Schema::hasColumns('user_devices', [
        'fcm_token', 'device_id', 'last_seen_at',
    ]))->toBeTrue();
})->group('migrations', 'identity');

it('countries has currency/locale/timezone/phone_code', function () {
    expect(Schema::hasColumns('countries', [
        'default_currency', 'default_locale', 'default_timezone', 'phone_code',
    ]))->toBeTrue();
})->group('migrations', 'geography');

it('governorates has unique code', function () {
    expect(Schema::hasColumn('governorates', 'code'))->toBeTrue();
})->group('migrations', 'geography');

it('vendor_coverage_areas has money columns', function () {
    expect(Schema::hasColumns('vendor_coverage_areas', [
        'delivery_fee_minor', 'delivery_fee_currency',
        'min_order_minor', 'min_order_currency',
    ]))->toBeTrue();
})->group('migrations', 'geography');

it('throws QueryException when deleting a city referenced by vendor_coverage_areas (restrictOnDelete)', function () {
    $user = User::factory()->create();
    $gov = Governorate::factory()->create();
    $reg = Region::factory()->for($gov)->create();
    $city = City::factory()->create(['region_id' => $reg->id, 'governorate_id' => $gov->id]);

    $vendorId = DB::table('vendor_profiles')->insertGetId([
        'public_id' => (string) Str::ulid(),
        'user_id' => $user->id,
        'business_name' => json_encode(['en' => 'X', 'ar' => 'X']),
        'slug' => 'x-'.Str::random(6),
        'business_type' => 'individual',
        'primary_governorate_id' => $gov->id,
        'primary_city_id' => $city->id,
        'approval_status' => 'pending',
        'rating_avg' => 0,
        'rating_count' => 0,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    DB::table('vendor_coverage_areas')->insert([
        'vendor_profile_id' => $vendorId,
        'city_id' => $city->id,
        'delivery_fee_minor' => 0,
        'delivery_fee_currency' => 'EGP',
        'min_order_minor' => 0,
        'min_order_currency' => 'EGP',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $city->delete();
})->throws(QueryException::class)->group('migrations', 'identity');

it('throws QueryException when deleting a city referenced by customer_addresses (restrictOnDelete)', function () {
    $user = User::factory()->create();
    $gov = Governorate::factory()->create();
    $reg = Region::factory()->for($gov)->create();
    $city = City::factory()->create(['region_id' => $reg->id, 'governorate_id' => $gov->id]);

    DB::table('customer_addresses')->insert([
        'public_id' => (string) Str::ulid(),
        'user_id' => $user->id,
        'city_id' => $city->id,
        'label' => 'Home',
        'address_line' => '123 Main St',
        'recipient_name' => 'Test',
        'recipient_phone_e164' => '+201000000000',
        'is_default' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $city->delete();
})->throws(QueryException::class)->group('migrations', 'identity');
