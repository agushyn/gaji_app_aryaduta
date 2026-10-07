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
        Schema::create('payslips', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payroll_period_id')->constrained('payroll_periods')->restrictOnDelete();
            $table->foreignId('employee_id')->constrained('employees')->restrictOnDelete();
            $table->string('slip_number', 80)->unique();

            // Employee data snapshot at payslip generation
            $table->string('employee_number_snapshot', 50);
            $table->string('employee_name_snapshot', 190);
            $table->string('department_snapshot', 150)->nullable();
            $table->string('position_snapshot', 150)->nullable();
            $table->string('email_snapshot', 190)->nullable();
            $table->string('phone_snapshot', 30)->nullable();

            // Financial summary
            $table->decimal('basic_salary', 15, 2)->default(0);
            $table->decimal('total_earnings', 15, 2)->default(0);
            $table->decimal('total_deductions', 15, 2)->default(0);
            $table->decimal('net_salary', 15, 2)->default(0);
            $table->char('currency', 3)->default('IDR');

            // Generated PDF metadata
            $table->string('pdf_path', 500)->nullable();
            $table->char('pdf_sha256', 64)->nullable();
            $table->timestamp('pdf_generated_at')->nullable();

            // Lifecycle and Audit Trail
            $table->string('status', 30)->default('draft');
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('submitted_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->text('approval_comment')->nullable();
            $table->foreignId('finalized_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('finalized_at')->nullable();
            $table->foreignId('voided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('voided_at')->nullable();
            $table->text('void_reason')->nullable();

            $table->timestamps();

            $table->unique(['payroll_period_id', 'employee_id']);
            $table->index(['status', 'payroll_period_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payslips');
    }
};
