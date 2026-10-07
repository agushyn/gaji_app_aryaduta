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
        Schema::table('employees', function (Blueprint $table) {
            $table->foreignId('office_id')->nullable()->after('full_name')->constrained('offices')->restrictOnDelete();
            $table->foreignId('position_id')->nullable()->after('division_id')->constrained('positions')->restrictOnDelete();

            $table->index(['office_id', 'division_id']);
            $table->index('position_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropForeign(['office_id']);
            $table->dropForeign(['position_id']);
            $table->dropColumn(['office_id', 'position_id']);
        });
    }
};
