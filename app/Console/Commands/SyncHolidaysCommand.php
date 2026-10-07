<?php

namespace App\Console\Commands;

use App\Services\HolidaySyncService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Throwable;

class SyncHolidaysCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'payroll:sync-holidays
                            {--year= : Tahun kalender libur yang akan disinkronkan (default: tahun berjalan)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sinkronisasi kalender hari libur nasional Indonesia dan cuti bersama ke database lokal';

    /**
     * Execute the console command.
     */
    public function handle(HolidaySyncService $syncService): int
    {
        $yearOption = $this->option('year');
        $year = $yearOption ? (int) $yearOption : Carbon::now()->year;

        $this->info("Memulai sinkronisasi kalender hari libur Indonesia untuk tahun {$year}...");

        try {
            $result = $syncService->syncYear($year);

            $this->table(
                ['Parameter', 'Nilai'],
                [
                    ['Tahun', (string) $result['year']],
                    ['Jumlah Tersinkron', (string) $result['synced']],
                    ['Sumber Data', $result['source']],
                    ['Terakhir Disinkronkan', $result['last_synced_at']],
                ]
            );

            $this->info("Sinkronisasi kalender tahun {$year} selesai dengan sukses.");

            return self::SUCCESS;
        } catch (Throwable $e) {
            $this->error("Gagal melakukan sinkronisasi kalender libur: {$e->getMessage()}");

            return self::FAILURE;
        }
    }
}
