<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('branding_settings', function (Blueprint $table): void {
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';

            $table->bigIncrements('id');
            $table->char('public_id', 26)->unique();
            $table->unsignedTinyInteger('singleton')->default(1);
            $table->json('site_name');
            $table->json('tagline')->nullable();
            $table->string('support_email', 191)->nullable();
            $table->string('support_phone', 32)->nullable();
            $table->string('whatsapp_number', 32)->nullable();
            $table->json('social')->nullable();
            $table->json('address_line')->nullable();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique('singleton', 'branding_settings_singleton_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('branding_settings');
    }
};
