<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_chargebacks', function (Blueprint $table) {
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';

            $table->bigIncrements('id');
            $table->char('public_id', 26)->unique();

            $table->foreignId('payment_id')
                ->constrained('payments')
                ->restrictOnDelete();

            $table->string('gateway_case_id', 190)->nullable()->index();

            $table->json('reason');

            $table->enum('status', ['open', 'under_review', 'won', 'lost'])
                ->default('open');

            $table->unsignedBigInteger('amount_minor');
            $table->char('amount_currency', 3);

            $table->timestamp('opened_at');
            $table->timestamp('resolved_at')->nullable();

            $table->json('admin_notes')->nullable();

            $table->foreignId('created_by')
                ->constrained('users')
                ->restrictOnDelete();

            $table->foreignId('updated_by')
                ->nullable()
                ->constrained('users')
                ->restrictOnDelete();

            $table->timestamps();

            $table->index(['payment_id']);
            $table->index(['status', 'opened_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_chargebacks');
    }
};
