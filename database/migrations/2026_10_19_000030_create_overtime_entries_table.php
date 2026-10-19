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
        Schema::create('overtime_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payroll_period_id')->constrained('payroll_periods')->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('employees')->restrictOnDelete();
            $table->date('date');
            $table->string('day_type', 30)->default('working_day'); // 'working_day' or 'holiday'
            $table->string('start_time', 10); // '17:00'
            $table->string('end_time', 10);   // '20:00'
            $table->unsignedInteger('duration_minutes'); // Exact duration in minutes
            $table->decimal('duration_hours', 8, 2);    // Decimal hours e.g. 3.00
            $table->text('note')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['payroll_period_id', 'employee_id']);
            $table->index(['payroll_period_id', 'date']);
            $table->index(['employee_id', 'date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('overtime_entries');
    }
};
