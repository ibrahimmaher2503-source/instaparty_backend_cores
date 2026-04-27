<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vendor_approved_product_types', function (Blueprint $table) {
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';

            $table->bigIncrements('id');
            $table->foreignId('vendor_profile_id')->constrained()->restrictOnDelete();
            $table->enum('product_type', ['rental', 'sale', 'digital']);
            $table->timestamp('approved_at')->useCurrent();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('revoked_at')->nullable();
            $table->foreignId('revoked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->json('revoke_reason')->nullable();
            $table->timestamps();

            $table->unique(['vendor_profile_id', 'product_type', 'revoked_at']);
            $table->index(['product_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vendor_approved_product_types');
    }
};
