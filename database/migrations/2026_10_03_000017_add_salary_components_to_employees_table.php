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
            // Komponen Gaji Tetap (Monthly Fixed Components)
            $table->decimal('gaji_pokok', 15, 2)->default(0)->after('employment_status');
            $table->decimal('tunjangan_kehadiran', 15, 2)->default(0)->after('gaji_pokok');
            $table->decimal('tunjangan_keluarga', 15, 2)->default(0)->after('tunjangan_kehadiran');
            $table->decimal('tunjangan_jabatan', 15, 2)->default(0)->after('tunjangan_keluarga');

            // Tarif Tunjangan Berdasarkan Hari (Daily Allowance Rates)
            $table->decimal('uang_makan_per_hari', 15, 2)->default(0)->after('tunjangan_jabatan');
            $table->decimal('uang_transportasi_per_hari', 15, 2)->default(0)->after('uang_makan_per_hari');

            // Potongan BPJS Ketenagakerjaan
            $table->boolean('bpjs_ketenagakerjaan_enabled')->default(false)->after('uang_transportasi_per_hari');
            $table->decimal('bpjs_ketenagakerjaan_amount', 15, 2)->default(0)->after('bpjs_ketenagakerjaan_enabled');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn([
                'gaji_pokok',
                'tunjangan_kehadiran',
                'tunjangan_keluarga',
                'tunjangan_jabatan',
                'uang_makan_per_hari',
                'uang_transportasi_per_hari',
                'bpjs_ketenagakerjaan_enabled',
                'bpjs_ketenagakerjaan_amount',
            ]);
        });
    }
};
