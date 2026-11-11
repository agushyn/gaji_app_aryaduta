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
        Schema::table('security_overtime_summaries', function (Blueprint $table) {
            // Target hours & Shortage tracking
            $table->decimal('target_hours', 8, 2)->default(0.00)->after('attendance_hours');
            $table->decimal('current_shortage_hours', 8, 2)->default(0.00)->after('target_hours');
            $table->decimal('previous_shortage_hours', 8, 2)->default(0.00)->after('current_shortage_hours');
            $table->decimal('total_shortage_hours', 8, 2)->default(0.00)->after('previous_shortage_hours');
            $table->decimal('shortage_offset_hours', 8, 2)->default(0.00)->after('total_shortage_hours');
            $table->decimal('remaining_shortage_hours', 8, 2)->default(0.00)->after('shortage_offset_hours');
            $table->foreignId('previous_payroll_period_id')->nullable()->after('remaining_shortage_hours')->constrained('payroll_periods')->nullOnDelete();

            // Breakdown of Regular Overtime
            $table->decimal('regular_break_deduction_hours', 8, 2)->default(0.00)->after('regular_overtime_hours');
            $table->decimal('regular_net_overtime_hours', 8, 2)->default(0.00)->after('regular_break_deduction_hours');
            $table->decimal('regular_first_hours', 8, 2)->default(0.00)->after('regular_net_overtime_hours');
            $table->decimal('regular_first_hours_cost', 15, 2)->default(0.00)->after('regular_first_hours');
            $table->decimal('regular_subsequent_hours', 8, 2)->default(0.00)->after('regular_first_hours_cost');
            $table->decimal('regular_subsequent_hours_cost', 15, 2)->default(0.00)->after('regular_subsequent_hours');
            $table->decimal('regular_overtime_cost', 15, 2)->default(0.00)->after('regular_subsequent_hours_cost');

            // Breakdown of OFF / Holiday Overtime
            $table->decimal('off_holiday_break_deduction_hours', 8, 2)->default(0.00)->after('off_holiday_overtime_hours');
            $table->decimal('off_holiday_net_overtime_hours', 8, 2)->default(0.00)->after('off_holiday_break_deduction_hours');
            $table->decimal('off_holiday_multiplier', 5, 2)->default(1.00)->after('off_holiday_net_overtime_hours');
            $table->decimal('off_holiday_overtime_cost', 15, 2)->default(0.00)->after('off_holiday_multiplier');
            $table->boolean('off_holiday_rate_confirmed')->default(false)->after('off_holiday_overtime_cost');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('security_overtime_summaries', function (Blueprint $table) {
            $table->dropForeign(['previous_payroll_period_id']);
            $table->dropColumn([
                'target_hours',
                'current_shortage_hours',
                'previous_shortage_hours',
                'total_shortage_hours',
                'shortage_offset_hours',
                'remaining_shortage_hours',
                'previous_payroll_period_id',
                'regular_break_deduction_hours',
                'regular_net_overtime_hours',
                'regular_first_hours',
                'regular_first_hours_cost',
                'regular_subsequent_hours',
                'regular_subsequent_hours_cost',
                'regular_overtime_cost',
                'off_holiday_break_deduction_hours',
                'off_holiday_net_overtime_hours',
                'off_holiday_multiplier',
                'off_holiday_overtime_cost',
                'off_holiday_rate_confirmed',
            ]);
        });
    }
};

