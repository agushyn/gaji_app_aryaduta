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
            $table->unsignedInteger('present_days')->default(0)->after('employee_id');
            $table->unsignedInteger('off_days')->default(0)->after('present_days');
            $table->unsignedInteger('sick_days')->default(0)->after('off_days');
            $table->unsignedInteger('permission_days')->default(0)->after('sick_days');
            $table->unsignedInteger('leave_days')->default(0)->after('permission_days');
            $table->unsignedInteger('absent_days')->default(0)->after('leave_days');
            $table->decimal('attendance_hours', 8, 2)->default(0.00)->after('absent_days');

            $table->unsignedInteger('regular_overtime_days')->default(0)->after('attendance_hours');
            $table->decimal('regular_overtime_hours', 8, 2)->default(0.00)->after('regular_overtime_days');
            $table->unsignedInteger('off_holiday_overtime_days')->default(0)->after('regular_overtime_hours');
            $table->decimal('off_holiday_overtime_hours', 8, 2)->default(0.00)->after('off_holiday_overtime_days');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('security_overtime_summaries', function (Blueprint $table) {
            $table->dropColumn([
                'present_days',
                'off_days',
                'sick_days',
                'permission_days',
                'leave_days',
                'absent_days',
                'attendance_hours',
                'regular_overtime_days',
                'regular_overtime_hours',
                'off_holiday_overtime_days',
                'off_holiday_overtime_hours',
            ]);
        });
    }
};

