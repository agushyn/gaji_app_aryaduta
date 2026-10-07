<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('divisions', function (Blueprint $table) {
            $table->foreignId('office_id')->nullable()->after('id')->constrained('offices')->restrictOnDelete();
            $table->unsignedBigInteger('department_id')->nullable()->change();
            $table->index(['office_id', 'is_active']);
        });

        // Drop global unique index on code to allow same division code across different offices if needed
        try {
            Schema::table('divisions', function (Blueprint $table) {
                $table->dropUnique(['code']);
            });
        } catch (\Throwable $e) {
            // In SQLite memory or environments where index drop is handled differently
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('divisions', function (Blueprint $table) {
            $table->dropForeign(['office_id']);
            $table->dropColumn('office_id');
            $table->unique('code');
        });
    }
};
