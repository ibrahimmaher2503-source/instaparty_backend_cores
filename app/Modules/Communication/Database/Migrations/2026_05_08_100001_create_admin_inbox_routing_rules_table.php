<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admin_inbox_routing_rules', function (Blueprint $table) {
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';

            $table->bigIncrements('id');
            $table->char('public_id', 26)->unique();

            $table->string('event_key', 100);
            $table->enum('severity', ['info', 'warning', 'critical']);

            $table->unsignedBigInteger('route_to_role_id')->nullable();
            $table->foreign('route_to_role_id')->references('id')->on('roles')->nullOnDelete();

            $table->unsignedBigInteger('route_to_admin_id')->nullable();
            $table->foreign('route_to_admin_id')->references('id')->on('users')->nullOnDelete();

            $table->boolean('is_active')->default(true);

            $table->unsignedBigInteger('created_by')->nullable();
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();

            $table->unsignedBigInteger('updated_by')->nullable();
            $table->foreign('updated_by')->references('id')->on('users')->nullOnDelete();

            $table->timestamps();

            $table->index(['event_key', 'severity', 'is_active'], 'airr_event_severity_active_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_inbox_routing_rules');
    }
};
