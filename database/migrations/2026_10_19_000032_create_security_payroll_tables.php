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
        // 1. Security Payroll Configurations
        Schema::create('security_payroll_configs', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique();
            $table->string('name');
            $table->decimal('hourly_rate', 12, 2)->default(14000.00);
            $table->decimal('first_hours_limit', 8, 2)->default(5.00);
            $table->decimal('first_hours_multiplier', 5, 2)->default(1.50);
            $table->decimal('subsequent_hours_multiplier', 5, 2)->default(2.00);
            $table->string('break_deduction_rule', 50)->default('fixed'); // fixed, hourly_threshold, manual
            $table->decimal('break_deduction_default', 8, 2)->default(0.00);
            $table->decimal('max_break_deduction', 8, 2)->default(20.00);
            $table->decimal('weekday_multiplier', 5, 2)->default(1.00);
            $table->decimal('holiday_multiplier', 5, 2)->default(1.00);
            $table->string('round_rule', 30)->default('round'); // round, floor, ceil
            $table->unsignedInteger('version')->default(1);
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        // 2. Security Attendances (Daily Records)
        Schema::create('security_attendances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payroll_period_id')->constrained('payroll_periods')->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->date('date');
            $table->string('status', 20)->default('present'); // present, off, absent, sick, leave
            $table->decimal('work_hours', 5, 2)->default(8.00);
            $table->decimal('overtime_hours', 5, 2)->default(0.00);
            $table->string('note')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['payroll_period_id', 'employee_id', 'date'], 'sec_att_period_emp_date_unique');
            $table->index(['payroll_period_id', 'employee_id'], 'sec_att_period_emp_index');
            $table->index(['date'], 'sec_att_date_index');
        });

        // 3. Security Overtime Summaries & Snapshots
        Schema::create('security_overtime_summaries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payroll_period_id')->constrained('payroll_periods')->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->unsignedInteger('total_days')->default(0);
            $table->unsignedInteger('total_work_days')->default(0);
            $table->unsignedInteger('total_off_days')->default(0);
            $table->unsignedInteger('total_absent_days')->default(0);
            $table->decimal('total_work_hours', 8, 2)->default(0.00);
            $table->decimal('gross_overtime_hours', 8, 2)->default(0.00);
            $table->decimal('break_deduction_hours', 8, 2)->default(0.00);
            $table->decimal('net_overtime_hours', 8, 2)->default(0.00);
            $table->decimal('hourly_rate', 12, 2)->default(14000.00);
            $table->decimal('first_hours_limit', 8, 2)->default(5.00);
            $table->decimal('first_hours_multiplier', 5, 2)->default(1.50);
            $table->decimal('subsequent_hours_multiplier', 5, 2)->default(2.00);
            $table->decimal('first_hours', 8, 2)->default(0.00);
            $table->decimal('first_hours_cost', 15, 2)->default(0.00);
            $table->decimal('subsequent_hours', 8, 2)->default(0.00);
            $table->decimal('subsequent_hours_cost', 15, 2)->default(0.00);
            $table->decimal('total_overtime_cost', 15, 2)->default(0.00);
            $table->decimal('total_period_salary', 15, 2)->default(0.00);
            $table->unsignedInteger('config_version')->default(1);
            $table->json('calculation_snapshot')->nullable();
            $table->timestamps();

            $table->unique(['payroll_period_id', 'employee_id'], 'sec_ot_summary_period_emp_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('security_overtime_summaries');
        Schema::dropIfExists('security_attendances');
        Schema::dropIfExists('security_payroll_configs');
    }
};
