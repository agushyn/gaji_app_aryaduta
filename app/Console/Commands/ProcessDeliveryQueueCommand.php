<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Throwable;

class ProcessDeliveryQueueCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'payroll:process-queue
                            {--limit=30 : Batas maksimum job yang diproses per batch}
                            {--timeout=60 : Batas waktu maksimal eksekusi worker dalam detik}
                            {--tries=3 : Jumlah percobaan maksimal per job sebelum ditandai failed}
                            {--sleep=1 : Waktu istirahat (detik) saat antrean kosong}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Memproses antrean pengiriman slip gaji secara terbatas untuk shared hosting / cPanel tanpa daemon permanen';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $limit = (int) $this->option('limit');
        $timeout = (int) $this->option('timeout');
        $tries = (int) $this->option('tries');
        $sleep = (int) $this->option('sleep');

        $this->info("Menjalankan runner antrean pengiriman slip gaji (Limit: {$limit}, Timeout: {$timeout}s)...");

        // Prevent overlapping execution across cron triggers using atomic lock
        $lock = Cache::lock('payroll_queue_runner_lock', $timeout + 10);

        if (! $lock->get()) {
            $this->warn('Proses antrean lain sedang berjalan. Eksekusi ini dilewati untuk menghindari benturan worker.');

            return self::SUCCESS;
        }

        try {
            // Check pending job count in database queue table
            $pendingCount = DB::table('jobs')->count();
            $this->line("Jumlah antrean pekerjaan di database saat ini: {$pendingCount}");

            if ($pendingCount === 0) {
                $this->info('Tidak ada pekerjaan dalam antrean. Selesai.');

                return self::SUCCESS;
            }

            // Execute Laravel queue worker in batch-limited mode
            $exitCode = Artisan::call('queue:work', [
                '--stop-when-empty' => true,
                '--max-jobs' => $limit,
                '--timeout' => $timeout,
                '--tries' => $tries,
                '--rest' => $sleep,
            ], $this->output);

            $remainingCount = DB::table('jobs')->count();
            $processedCount = max(0, $pendingCount - $remainingCount);

            $this->info("Batch selesai diproses. Pekerjaan diselesaikan: {$processedCount}. Sisa antrean: {$remainingCount}.");

            return $exitCode;
        } catch (Throwable $e) {
            $this->error("Terjadi kesalahan saat memproses antrean: {$e->getMessage()}");

            return self::FAILURE;
        } finally {
            $lock->release();
        }
    }
}
