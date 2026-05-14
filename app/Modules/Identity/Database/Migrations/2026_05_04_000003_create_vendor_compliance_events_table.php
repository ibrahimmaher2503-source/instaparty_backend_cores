<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vendor_compliance_events', function (Blueprint $table) {
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';

            $table->bigIncrements('id');
            $table->char('public_id', 26)->unique();
            $table->foreignId('vendor_profile_id')->constrained()->restrictOnDelete();
            $table->foreignId('document_id')->nullable()->constrained('vendor_documents')->nullOnDelete();
            $table->enum('event_type', ['reminder_sent', 'expired', 'auto_suspended', 'manually_overridden']);
            $table->timestamp('occurred_at')->useCurrent();
            $table->foreignId('admin_id')->nullable()->constrained('users')->nullOnDelete();
            $table->json('reason')->nullable();

            // Append-only constraints
            // NO updated_at, NO softDeletes()

            // Indexes
            $table->index(['vendor_profile_id', 'event_type', 'occurred_at'], 'idx_vce_vendor_event_time');
            $table->index(['document_id', 'event_type'], 'idx_vce_doc_event');
            $table->index(['event_type', 'occurred_at'], 'idx_vce_event_time');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vendor_compliance_events');
    }
};
