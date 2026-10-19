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
        Schema::table('positions', function (Blueprint $table) {
            $table->string('payroll_type', 30)->default('regular')->after('code');
        });

        Schema::table('employees', function (Blueprint $table) {
            $table->string('payroll_type', 30)->nullable()->default(null)->after('position_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn('payroll_type');
        });

        Schema::table('positions', function (Blueprint $table) {
            $table->dropColumn('payroll_type');
        });
    }
};
