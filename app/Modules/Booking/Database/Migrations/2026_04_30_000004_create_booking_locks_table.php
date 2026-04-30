<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('booking_locks', function (Blueprint $table): void {
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';

            $table->bigIncrements('id');
            $table->string('resource_type', 80);
            $table->unsignedBigInteger('resource_id');
            $table->char('lock_token', 36);

            $table->unsignedBigInteger('locked_by_user_id')->nullable();
            $table->foreign('locked_by_user_id')->references('id')->on('users')->nullOnDelete();

            $table->enum('lock_purpose', ['payment', 'modification', 'admin_action']);

            $table->dateTime('acquired_at');
            $table->dateTime('expires_at');
            $table->dateTime('released_at')->nullable();

            $table->timestamp('created_at')->useCurrent();

            // Intentional nullable unique: multiple released_at=NULL rows blocked (one active lock per resource)
            $table->unique(['resource_type', 'resource_id', 'released_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('booking_locks');
    }
};
