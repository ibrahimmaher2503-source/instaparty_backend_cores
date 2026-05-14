<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vendor_profiles', function (Blueprint $table) {
            $table->enum('approval_status', ['pending', 'approved', 'rejected', 'suspended', 'changes_requested'])
                ->change();
        });
    }

    public function down(): void
    {
        Schema::table('vendor_profiles', function (Blueprint $table) {
            $table->enum('approval_status', ['pending', 'approved', 'rejected', 'suspended'])
                ->change();
        });
    }
};
