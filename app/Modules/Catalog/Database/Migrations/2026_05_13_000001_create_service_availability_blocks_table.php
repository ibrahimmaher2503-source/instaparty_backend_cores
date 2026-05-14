<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_availability_blocks', function (Blueprint $table): void {
            $table->charset   = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';

            $table->bigIncrements('id');
            $table->char('public_id', 26)->unique();
            $table->foreignId('service_id')->constrained()->cascadeOnDelete();
            $table->timestamp('starts_at');
            $table->timestamp('ends_at');
            $table->json('reason')->nullable();
            $table->timestamps();

            $table->index(['service_id', 'starts_at', 'ends_at'], 'sab_service_starts_ends_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_availability_blocks');
    }
};
