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
        Schema::create('payroll_calculation_configs', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150);
            $table->string('code', 50)->unique();
            $table->foreignId('component_id')->nullable()->constrained('pay_components')->nullOnDelete();
            $table->string('day_type', 50)->default('working_day'); // working_day, holiday, weekend, all
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('current_version')->default(1);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['component_id', 'day_type', 'is_active'], 'pcc_comp_day_active_idx');
        });

        Schema::create('payroll_calculation_config_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('config_id')->constrained('payroll_calculation_configs')->cascadeOnDelete();
            $table->unsignedInteger('version')->default(1);
            $table->json('formula');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['config_id', 'version'], 'pccv_config_version_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payroll_calculation_config_versions');
        Schema::dropIfExists('payroll_calculation_configs');
    }
};
