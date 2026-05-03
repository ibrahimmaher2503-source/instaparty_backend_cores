<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('loyalty_programs', function (Blueprint $table) {
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';

            $table->bigIncrements('id');
            $table->char('public_id', 26)->unique();
            $table->foreignId('vendor_profile_id')->unique()->constrained('vendor_profiles')->restrictOnDelete();
            $table->json('name');
            $table->json('terms')->nullable();
            $table->char('currency', 3)->default('EGP');
            $table->enum('status', ['active', 'paused', 'archived'])->default('active');
            $table->smallInteger('expiration_days')->unsigned()->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('loyalty_programs');
    }
};
