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
        Schema::create('payslip_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payslip_id')->constrained('payslips')->cascadeOnDelete();
            $table->foreignId('pay_component_id')->nullable()->constrained('pay_components')->restrictOnDelete();
            $table->string('item_code_snapshot', 50);
            $table->string('description_snapshot', 190);
            $table->string('item_type', 20); // earning or deduction
            $table->decimal('amount', 15, 2);
            $table->smallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['payslip_id', 'sort_order']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payslip_items');
    }
};
