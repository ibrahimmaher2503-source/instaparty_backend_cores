<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('booking_modification_items', function (Blueprint $table): void {
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';
            $table->bigIncrements('id');
            $table->foreignId('booking_modification_id')->constrained('booking_modifications')->cascadeOnDelete();
            $table->foreignId('target_booking_item_id')->nullable()->constrained('booking_items')->nullOnDelete();
            $table->enum('change_kind', ['add', 'remove', 'update']);
            $table->json('payload');
            $table->timestamp('created_at')->useCurrent();

            $table->index('booking_modification_id');
            $table->index('target_booking_item_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('booking_modification_items');
    }
};
