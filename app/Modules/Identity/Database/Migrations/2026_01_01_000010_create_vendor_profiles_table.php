<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vendor_profiles', function (Blueprint $table) {
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';

            $table->bigIncrements('id');
            $table->char('public_id', 26)->unique();
            $table->foreignId('user_id')->unique()->constrained()->restrictOnDelete();

            $table->json('business_name');
            $table->string('slug', 160)->unique();
            $table->json('bio')->nullable();
            $table->string('logo_path', 512)->nullable();
            $table->string('cover_path', 512)->nullable();

            $table->enum('business_type', ['individual', 'company', 'establishment']);
            $table->string('commercial_register_no', 50)->nullable();
            $table->string('tax_id', 50)->nullable();
            $table->string('national_id', 50)->nullable();

            $table->foreignId('primary_governorate_id')->constrained('governorates')->restrictOnDelete();
            $table->foreignId('primary_city_id')->constrained('cities')->restrictOnDelete();
            $table->json('address_line')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();

            $table->enum('approval_status', ['pending', 'approved', 'rejected', 'suspended'])->default('pending');
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->json('rejection_reason')->nullable();

            $table->string('bank_name', 120)->nullable();
            $table->string('bank_account_holder', 160)->nullable();
            $table->string('bank_iban', 34)->nullable();
            $table->string('bank_swift_bic', 11)->nullable();
            $table->string('bank_branch', 120)->nullable();

            $table->decimal('rating_avg', 3, 2)->default(0);
            $table->unsignedInteger('rating_count')->default(0);
            $table->unsignedInteger('response_time_avg_minutes')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['approval_status', 'deleted_at']);
            $table->index(['primary_city_id']);
            $table->index(['slug']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vendor_profiles');
    }
};
