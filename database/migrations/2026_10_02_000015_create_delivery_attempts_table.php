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
        Schema::create('delivery_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payslip_delivery_id')->constrained('payslip_deliveries')->cascadeOnDelete();
            $table->unsignedSmallInteger('attempt_number');
            $table->string('status', 30); // processing, sent, delivered, failed
            $table->string('provider_message_id', 190)->nullable();
            $table->string('error_code', 100)->nullable();
            $table->text('error_summary')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            $table->unique(['payslip_delivery_id', 'attempt_number']);
            $table->index(['payslip_delivery_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('delivery_attempts');
    }
};
