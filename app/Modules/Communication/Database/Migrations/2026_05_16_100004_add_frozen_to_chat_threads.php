<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('chat_threads', function (Blueprint $table): void {
            $table->timestamp('frozen_at')->nullable()->after('locked_at');
            $table->foreignId('frozen_by')->nullable()->after('frozen_at')
                ->constrained('users')->nullOnDelete();

            $table->index(['booking_id', 'frozen_at']);
        });
    }

    public function down(): void
    {
        Schema::table('chat_threads', function (Blueprint $table): void {
            $table->dropForeign(['frozen_by']);
            $table->dropIndex(['booking_id', 'frozen_at']);
            $table->dropColumn(['frozen_at', 'frozen_by']);
        });
    }
};
