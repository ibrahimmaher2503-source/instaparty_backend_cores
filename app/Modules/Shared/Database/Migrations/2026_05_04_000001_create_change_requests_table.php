<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('change_requests', function (Blueprint $table) {
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';

            $table->bigIncrements('id');
            $table->char('public_id', 26)->unique();
            $table->enum('subject_type', ['vendor_profile', 'service']);
            $table->unsignedBigInteger('subject_id');
            $table->foreignId('requested_by_admin_id')->constrained('users')->restrictOnDelete();
            $table->enum('status', ['open', 'resubmitted', 'resolved', 'escalated_to_rejection'])->default('open');
            $table->unsignedTinyInteger('cycle_number');
            $table->text('resolution_notes')->nullable();
            $table->foreignId('resolved_by_admin_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['subject_type', 'subject_id', 'status']);
            $table->index('requested_by_admin_id');
            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('change_requests');
    }
};
