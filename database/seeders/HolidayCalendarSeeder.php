<?php

namespace Database\Seeders;

use App\Services\HolidaySyncService;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class HolidayCalendarSeeder extends Seeder
{
    /**
     * Seed Indonesian holiday calendars (SKB 3 Menteri).
     */
    public function run(HolidaySyncService $syncService): void
    {
        $currentYear = Carbon::now()->year;
        $syncService->syncYear($currentYear);
    }
}
