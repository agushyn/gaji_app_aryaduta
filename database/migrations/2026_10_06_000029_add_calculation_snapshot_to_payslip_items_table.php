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
        Schema::table('payslip_items', function (Blueprint $table) {
            $table->foreignId('calculation_config_id')->nullable()->after('pay_component_id')->constrained('payroll_calculation_configs')->nullOnDelete();
            $table->unsignedInteger('calculation_config_version')->nullable()->after('calculation_config_id');
            $table->json('calculation_snapshot')->nullable()->after('note');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payslip_items', function (Blueprint $table) {
            $table->dropForeign(['calculation_config_id']);
            $table->dropColumn(['calculation_config_id', 'calculation_config_version', 'calculation_snapshot']);
        });
    }
};
