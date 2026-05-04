<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plan_features', function (Blueprint $table) {
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';

            $table->bigIncrements('id');

            $table->foreignId('subscription_plan_id')
                ->constrained('subscription_plans')
                ->cascadeOnDelete();

            $table->string('feature_key', 60);
            $table->enum('value_type', ['int', 'bool', 'string']);
            $table->unsignedBigInteger('value_int')->nullable();
            $table->boolean('value_bool')->nullable();
            $table->string('value_string', 255)->nullable();

            $table->json('label');

            $table->timestamps();

            $table->unique(['subscription_plan_id', 'feature_key']);
            $table->index('feature_key');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plan_features');
    }
};
