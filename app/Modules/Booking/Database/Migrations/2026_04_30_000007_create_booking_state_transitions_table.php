<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('booking_state_transitions', function (Blueprint $table): void {
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';

            $table->bigIncrements('id');
            $table->string('transitionable_type', 120);
            $table->unsignedBigInteger('transitionable_id');

            $table->string('from_state', 40)->nullable();
            $table->string('to_state', 40);

            $table->unsignedBigInteger('triggered_by')->nullable();
            $table->foreign('triggered_by')->references('id')->on('users')->nullOnDelete();

            $table->enum('trigger_kind', ['system', 'customer', 'vendor', 'admin']);
            $table->json('context')->nullable();

            $table->timestamp('created_at')->useCurrent();

            $table->index(['transitionable_type', 'transitionable_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('booking_state_transitions');
    }
};
