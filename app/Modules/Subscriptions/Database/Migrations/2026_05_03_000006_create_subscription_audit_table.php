<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Append-only ledger: no updated_at, no deleted_at, no UPDATE ever.
        Schema::create('subscription_audit', function (Blueprint $table) {
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';

            $table->bigIncrements('id');
            $table->char('public_id', 26)->unique();

            $table->foreignId('vendor_subscription_id')
                ->constrained('vendor_subscriptions')
                ->restrictOnDelete();

            $table->foreignId('vendor_profile_id')
                ->constrained('vendor_profiles')
                ->restrictOnDelete();

            $table->string('event_type', 60);

            $table->string('actor_type', 30)->nullable();
            $table->unsignedBigInteger('actor_id')->nullable();

            $table->json('before_state')->nullable();
            $table->json('after_state')->nullable();
            $table->json('metadata')->nullable();

            $table->text('reason')->nullable();

            $table->timestamp('created_at')->useCurrent();

            $table->index(['vendor_subscription_id', 'event_type']);
            $table->index(['vendor_profile_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscription_audit');
    }
};
