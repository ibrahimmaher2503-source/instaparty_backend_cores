<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('services', function (Blueprint $table): void {
            $table->dropIndex(['category_id', 'product_type', 'status']);
            $table->dropColumn('status');
        });

        Schema::table('services', function (Blueprint $table): void {
            $table->enum('status', ['draft', 'pending_review', 'changes_requested', 'published', 'archived'])->default('draft')->after('public_id');
            $table->index(['category_id', 'product_type', 'status']);
        });
    }

    public function down(): void
    {
        Schema::table('services', function (Blueprint $table): void {
            $table->dropColumn('status');
        });

        Schema::table('services', function (Blueprint $table): void {
            $table->enum('status', ['draft', 'pending_review', 'published', 'archived'])->default('draft')->after('public_id');
        });
    }
};
