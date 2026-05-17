<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('idempotency_keys', function (Blueprint $table): void {
            // Distinguishes inbound HTTP keys from internal Action keys
            $table->enum('scope', ['http', 'internal'])->default('http')->after('key');

            // Per-row TTL in seconds; existing rows default to 24h
            $table->unsignedInteger('ttl_seconds')->default(86400)->after('scope');

            // SHA-256 of canonical payload; detects mismatched-payload duplicates
            $table->char('payload_hash', 64)->nullable()->after('request_hash');
        });
    }

    public function down(): void
    {
        Schema::table('idempotency_keys', function (Blueprint $table): void {
            $table->dropColumn(['scope', 'ttl_seconds', 'payload_hash']);
        });
    }
};
