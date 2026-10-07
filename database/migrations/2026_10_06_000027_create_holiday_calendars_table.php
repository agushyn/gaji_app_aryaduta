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
        Schema::create('holiday_calendars', function (Blueprint $table) {
            $table->id();
            $table->date('date');
            $table->string('name', 150);
            $table->string('type', 50)->default('national_holiday'); // national_holiday, joint_leave
            $table->boolean('is_joint_leave')->default(false);
            $table->boolean('is_payroll_holiday')->default(true); // whether counted as paid holiday for payroll/overtime
            $table->unsignedSmallInteger('year');
            $table->string('source', 100)->default('skb_3_menteri');
            $table->string('source_id', 100)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['date', 'type'], 'holiday_date_type_unique');
            $table->index(['year', 'is_active']);
            $table->index(['date', 'is_active', 'is_payroll_holiday']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('holiday_calendars');
    }
};
