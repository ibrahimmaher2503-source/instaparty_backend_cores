<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * analytics_events — append-only event sink (LOCKED schema, cross-cutting).
 * Was specified in 11_DB_Schema but never migrated; both the Advertising
 * impression tracker and the customer service-view tracker write to it.
 * Append-only: created_at only, no updated_at, no soft deletes
 * (CLAUDE.md §15). Partition once >10M rows per the schema note.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('analytics_events', function (Blueprint $table): void {
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';

            $table->bigIncrements('id');
            $table->string('event_type', 80);
            $table->json('payload')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['event_type', 'created_at'], 'idx_analytics_type_created');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('analytics_events');
    }
};
