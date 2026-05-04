<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscription_plans', function (Blueprint $table) {
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';

            $table->bigIncrements('id');
            $table->char('public_id', 26)->unique();
            $table->string('plan_code', 20)->unique();

            $table->json('name');
            $table->json('description');

            $table->unsignedBigInteger('monthly_price_minor')->default(0);
            $table->char('monthly_price_currency', 3)->default('EGP');
            $table->unsignedBigInteger('yearly_price_minor')->default(0);
            $table->char('yearly_price_currency', 3)->default('EGP');

            $table->boolean('is_default')->default(false);
            $table->boolean('is_published')->default(true);
            $table->unsignedSmallInteger('display_order')->default(0);

            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->unsignedBigInteger('deleted_by')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index('is_published');
            $table->index('display_order');

            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('updated_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('deleted_by')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscription_plans');
    }
};
