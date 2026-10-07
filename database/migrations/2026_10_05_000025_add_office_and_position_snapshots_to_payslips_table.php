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
        Schema::table('payslips', function (Blueprint $table) {
            $table->foreignId('office_id')->nullable()->after('employee_id')->constrained('offices')->nullOnDelete();
            $table->string('office_name_snapshot', 150)->nullable()->after('company_address_snapshot');
            $table->string('office_code_snapshot', 50)->nullable()->after('office_name_snapshot');
            $table->text('office_address_snapshot')->nullable()->after('office_code_snapshot');
            $table->string('office_phone_snapshot', 50)->nullable()->after('office_address_snapshot');
            $table->string('office_email_snapshot', 150)->nullable()->after('office_phone_snapshot');
            $table->string('division_name_snapshot', 150)->nullable()->after('office_email_snapshot');
            $table->string('position_name_snapshot', 150)->nullable()->after('division_name_snapshot');

            $table->index(['office_id', 'payroll_period_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payslips', function (Blueprint $table) {
            $table->dropForeign(['office_id']);
            $table->dropColumn([
                'office_id',
                'office_name_snapshot',
                'office_code_snapshot',
                'office_address_snapshot',
                'office_phone_snapshot',
                'office_email_snapshot',
                'division_name_snapshot',
                'position_name_snapshot',
            ]);
        });
    }
};
