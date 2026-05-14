<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('change_request_items', function (Blueprint $table) {
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';

            $table->bigIncrements('id');
            $table->char('public_id', 26)->unique();
            $table->foreignId('change_request_id')->constrained('change_requests')->restrictOnDelete();
            $table->string('field_path', 255);
            $table->json('current_value_snapshot')->nullable();
            $table->text('requested_change_en');
            $table->text('requested_change_ar');
            $table->enum('item_status', ['pending', 'addressed', 'waived'])->default('pending');
            $table->timestamp('created_at')->useCurrent();

            $table->index(['change_request_id', 'item_status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('change_request_items');
    }
};
