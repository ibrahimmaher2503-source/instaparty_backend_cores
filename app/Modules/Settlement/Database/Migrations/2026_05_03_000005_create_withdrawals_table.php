<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('withdrawals', function (Blueprint $table): void {
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';

            $table->bigIncrements('id');
            $table->char('public_id', 26)->unique();

            $table->unsignedBigInteger('vendor_profile_id');
            $table->foreign('vendor_profile_id')
                ->references('id')
                ->on('vendor_profiles')
                ->restrictOnDelete();

            $table->bigInteger('requested_amount_minor');
            $table->char('requested_amount_currency', 3);

            $table->bigInteger('paid_amount_minor')->nullable();
            $table->char('paid_amount_currency', 3)->nullable();

            $table->json('bank_account_snapshot');

            $table->enum('status', ['pending', 'approved', 'paid', 'rejected'])
                ->default('pending');

            $table->json('rejected_reason')->nullable();

            $table->unsignedBigInteger('requested_by_user_id');
            $table->foreign('requested_by_user_id')
                ->references('id')
                ->on('users')
                ->restrictOnDelete();

            $table->unsignedBigInteger('processed_by_user_id')->nullable();
            $table->foreign('processed_by_user_id')
                ->references('id')
                ->on('users')
                ->restrictOnDelete();

            $table->unsignedBigInteger('bank_proof_media_id')->nullable();
            $table->foreign('bank_proof_media_id')
                ->references('id')
                ->on('media')
                ->restrictOnDelete();

            $table->timestamp('requested_at');
            $table->timestamp('processed_at')->nullable();
            $table->timestamp('paid_at')->nullable();

            // Single-pending-per-vendor guard (application-level since MySQL 8 has no partial unique index)
            // Set to vendor_profile_id when status='pending', NULL otherwise
            $table->unsignedBigInteger('pending_lock')
                ->nullable()
                ->unique()
                ->comment('vendor_profile_id when status=pending, NULL otherwise');

            $table->timestamps();

            $table->index(['status', 'created_at'], 'withdrawals_status_created_index');
            $table->index(
                ['vendor_profile_id', 'status', 'created_at'],
                'withdrawals_vendor_status_created_index'
            );
            $table->index(
                ['processed_by_user_id', 'processed_at'],
                'withdrawals_processor_index'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('withdrawals');
    }
};
