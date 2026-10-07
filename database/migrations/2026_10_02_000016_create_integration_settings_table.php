<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('integration_settings', function (Blueprint $table) {
            $table->id();
            $table->string('provider', 100)->unique(); // 'smtp', 'whatsapp'
            $table->boolean('is_active')->default(false);
            $table->string('endpoint', 500)->nullable(); // e.g. host
            $table->string('sender_identifier', 190)->nullable(); // e.g. from address / sender phone
            $table->json('config_json')->nullable(); // non-secret config
            $table->longText('secret_encrypted')->nullable(); // encrypted secret
            $table->timestamp('last_tested_at')->nullable();
            $table->string('last_test_status', 30)->nullable(); // success, failed
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('integration_settings');
    }
};
