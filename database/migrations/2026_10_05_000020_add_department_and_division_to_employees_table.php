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
            $table->foreignId('department_id')
                ->nullable()
                ->after('full_name')
                ->constrained('departments')
                ->restrictOnDelete();

            $table->foreignId('division_id')
                ->nullable()
                ->after('department_id')
                ->constrained('divisions')
                ->restrictOnDelete();

            $table->index(['department_id', 'division_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropForeign(['department_id']);
            $table->dropForeign(['division_id']);
            $table->dropIndex(['department_id', 'division_id']);
            $table->dropColumn(['department_id', 'division_id']);
        });
    }
};
