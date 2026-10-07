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
        Schema::table('payroll_periods', function (Blueprint $table) {
            $table->unsignedSmallInteger('working_days')->default(25)->after('end_date');
            $table->string('period_month_label', 50)->nullable()->after('working_days');
        });

        Schema::table('payslips', function (Blueprint $table) {
            $table->unsignedSmallInteger('working_days_snapshot')->default(25)->after('phone_snapshot');
            $table->string('period_month_snapshot', 50)->nullable()->after('working_days_snapshot');
            $table->date('period_start_date_snapshot')->nullable()->after('period_month_snapshot');
            $table->date('period_end_date_snapshot')->nullable()->after('period_start_date_snapshot');
            $table->string('overtime_period_snapshot', 100)->nullable()->after('period_end_date_snapshot');

            $table->decimal('gaji_bruto', 15, 2)->default(0)->after('net_salary');
            $table->decimal('uang_dinas', 15, 2)->default(0)->after('gaji_bruto');

            $table->string('company_name_snapshot', 150)->nullable()->after('currency');
            $table->text('company_address_snapshot')->nullable()->after('company_name_snapshot');

            $table->string('signatory_personalia_name', 150)->nullable()->after('pdf_generated_at');
            $table->string('signatory_personalia_title', 100)->nullable()->after('signatory_personalia_name');
            $table->string('signatory_reliever_name', 150)->nullable()->after('signatory_personalia_title');
            $table->string('signatory_reliever_title', 100)->nullable()->after('signatory_reliever_name');
            $table->string('signatory_receiver_name', 150)->nullable()->after('signatory_reliever_title');
        });

        Schema::table('payslip_items', function (Blueprint $table) {
            $table->string('category', 50)->nullable()->after('item_type');
            $table->decimal('quantity', 8, 2)->nullable()->after('category');
            $table->string('unit', 30)->nullable()->after('quantity');
            $table->decimal('rate', 15, 2)->nullable()->after('unit');
            $table->string('note', 150)->nullable()->after('rate');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payslip_items', function (Blueprint $table) {
            $table->dropColumn(['category', 'quantity', 'unit', 'rate', 'note']);
        });

        Schema::table('payslips', function (Blueprint $table) {
            $table->dropColumn([
                'working_days_snapshot',
                'period_month_snapshot',
                'period_start_date_snapshot',
                'period_end_date_snapshot',
                'overtime_period_snapshot',
                'gaji_bruto',
                'uang_dinas',
                'company_name_snapshot',
                'company_address_snapshot',
                'signatory_personalia_name',
                'signatory_personalia_title',
                'signatory_reliever_name',
                'signatory_reliever_title',
                'signatory_receiver_name',
            ]);
        });

        Schema::table('payroll_periods', function (Blueprint $table) {
            $table->dropColumn(['working_days', 'period_month_label']);
        });
    }
};
