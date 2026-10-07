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
        Schema::create('payslip_deliveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payslip_id')->constrained('payslips')->restrictOnDelete();
            $table->string('channel', 20); // 'email', 'whatsapp'
            $table->string('recipient', 190);
            $table->string('status', 30)->default('queued'); // queued, processing, sent, delivered, failed, unknown, cancelled
            $table->string('provider_message_id', 190)->nullable();
            $table->string('idempotency_key', 100)->unique();
            $table->unsignedSmallInteger('attempt_count')->default(0);
            $table->timestamp('last_attempt_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->string('last_error_code', 100)->nullable();
            $table->text('last_error_summary')->nullable();
            $table->timestamps();

            $table->unique(['payslip_id', 'channel']);
            $table->index(['status', 'channel']);
            $table->index(['channel', 'recipient']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payslip_deliveries');
    }
};
