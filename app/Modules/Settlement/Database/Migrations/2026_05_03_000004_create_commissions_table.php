<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('commissions', function (Blueprint $table): void {
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';

            $table->bigIncrements('id');
            $table->char('public_id', 26)->unique();

            $table->foreignId('booking_item_id')
                ->unique()
                ->constrained('booking_items')
                ->restrictOnDelete();

            $table->foreignId('payment_id')
                ->constrained('payments')
                ->restrictOnDelete();

            $table->unsignedBigInteger('vendor_profile_id');
            $table->foreign('vendor_profile_id')
                ->references('id')
                ->on('vendor_profiles')
                ->restrictOnDelete();

            $table->unsignedBigInteger('category_id')->nullable();
            $table->foreign('category_id')
                ->references('id')
                ->on('categories')
                ->restrictOnDelete();

            $table->enum('product_type', ['rental', 'sale', 'digital']);

            $table->bigInteger('gross_amount_minor');
            $table->char('gross_amount_currency', 3);

            $table->integer('commission_bps');

            $table->bigInteger('commission_minor');
            $table->char('commission_currency', 3);

            $table->bigInteger('vendor_share_minor');
            $table->char('vendor_share_currency', 3);

            $table->bigInteger('reversed_amount_minor')->default(0);

            $table->enum('status', ['calculated', 'partially_reversed', 'reversed'])
                ->default('calculated');

            // Append-only: only created_at, no updated_at
            $table->timestamp('created_at')->useCurrent();

            $table->index(
                ['vendor_profile_id', 'status', 'created_at'],
                'commissions_vendor_status_created_index'
            );
            $table->index('payment_id', 'commissions_payment_id_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('commissions');
    }
};
