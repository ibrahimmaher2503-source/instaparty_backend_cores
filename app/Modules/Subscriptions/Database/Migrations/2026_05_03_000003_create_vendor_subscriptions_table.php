<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vendor_subscriptions', function (Blueprint $table) {
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';

            $table->bigIncrements('id');
            $table->char('public_id', 26)->unique();

            $table->foreignId('vendor_profile_id')
                ->constrained('vendor_profiles')
                ->restrictOnDelete();

            $table->foreignId('subscription_plan_id')
                ->constrained('subscription_plans')
                ->restrictOnDelete();

            $table->string('status', 20);
            $table->string('billing_cycle', 10);

            $table->timestamp('current_period_start')->nullable();
            $table->timestamp('current_period_end')->nullable();
            $table->timestamp('grace_period_ends_at')->nullable();

            $table->boolean('cancel_at_period_end')->default(false);

            $table->boolean('is_admin_override')->default(false);
            $table->text('override_reason')->nullable();
            $table->timestamp('override_expires_at')->nullable();

            $table->string('gateway_token_ref', 255)->nullable();

            $table->timestamp('started_at');
            $table->timestamp('ended_at')->nullable();
            $table->string('ended_reason', 50)->nullable();

            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();

            $table->timestamps();

            $table->index(['vendor_profile_id', 'status']);
            $table->index(['vendor_profile_id', 'is_admin_override', 'status']);
            $table->index(['status', 'current_period_end']);
            $table->index(['status', 'grace_period_ends_at']);
            $table->index(['status', 'override_expires_at']);

            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('updated_by')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vendor_subscriptions');
    }
};
