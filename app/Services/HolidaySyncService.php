<?php

namespace App\Services;

use App\Models\CompanySetting;
use App\Models\HolidayCalendar;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class HolidaySyncService
{
    /**
     * Synchronize Indonesian holidays for a given year.
     * Upserts into local holiday_calendars table so payroll never relies on external API at runtime.
     *
     * @return array{synced: int, year: int, source: string, last_synced_at: string}
     */
    public function syncYear(int $year): array
    {
        $apiUrl = config('services.holiday_api.url') ?? env('HOLIDAY_API_URL');
        $apiKey = config('services.holiday_api.key') ?? env('HOLIDAY_API_KEY');

        $holidaysData = null;
        $sourceName = 'skb_3_menteri';

        if (! empty($apiUrl)) {
            try {
                $client = Http::timeout(8);
                if ($apiKey) {
                    $client = $client->withHeaders(['Authorization' => "Bearer {$apiKey}"]);
                }

                $urlWithYear = str_contains($apiUrl, '{year}')
                    ? str_replace('{year}', (string) $year, $apiUrl)
                    : (str_contains($apiUrl, '?') ? "{$apiUrl}&tahun={$year}" : "{$apiUrl}?tahun={$year}");

                $response = $client->get($urlWithYear);

                if ($response->successful()) {
                    $json = $response->json();
                    $holidaysData = $this->normalizeApiResponse($json, $year);
                    $sourceName = 'api';
                }
            } catch (\Throwable $e) {
                Log::warning("Gagal menghubungi external holiday API: {$e->getMessage()}. Melakukan fallback ke data resmi SKB 3 Menteri.");
            }
        }

        // Fallback to built-in SKB 3 Menteri catalog if API was not configured or failed
        if ($holidaysData === null) {
            $holidaysData = $this->getOfficialCatalogForYear($year);
            $sourceName = 'skb_3_menteri_official';
        }

        $syncedCount = 0;

        foreach ($holidaysData as $item) {
            $date = Carbon::parse($item['date'])->toDateString();
            $type = $item['type'] ?? ($item['is_joint_leave'] ? 'joint_leave' : 'national_holiday');
            $isJointLeave = (bool) ($item['is_joint_leave'] ?? ($type === 'joint_leave'));

            // Check if record already exists using whereDate for full cross-database compatibility
            $existing = HolidayCalendar::whereDate('date', $date)
                ->where('type', $type)
                ->first();

            $isPayrollHoliday = $existing !== null
                ? $existing->is_payroll_holiday
                : ($type === 'national_holiday'); // default: national = true, joint leave = false

            if ($existing) {
                $existing->update([
                    'name' => trim($item['name']),
                    'is_joint_leave' => $isJointLeave,
                    'is_payroll_holiday' => $isPayrollHoliday,
                    'year' => $year,
                    'source' => $item['source'] ?? $sourceName,
                    'source_id' => $item['source_id'] ?? null,
                    'is_active' => true,
                ]);
            } else {
                HolidayCalendar::create([
                    'date' => $date,
                    'type' => $type,
                    'name' => trim($item['name']),
                    'is_joint_leave' => $isJointLeave,
                    'is_payroll_holiday' => $isPayrollHoliday,
                    'year' => $year,
                    'source' => $item['source'] ?? $sourceName,
                    'source_id' => $item['source_id'] ?? null,
                    'is_active' => true,
                ]);
            }

            $syncedCount++;
        }

        $nowIso = now()->toIso8601String();
        CompanySetting::set('holiday_last_synced_at', $nowIso);

        return [
            'synced' => $syncedCount,
            'year' => $year,
            'source' => $sourceName,
            'last_synced_at' => $nowIso,
        ];
    }

    /**
     * Normalize response from varying Indonesian holiday API structures.
     */
    protected function normalizeApiResponse(mixed $json, int $year): ?array
    {
        if (! is_array($json)) {
            return null;
        }

        $normalized = [];
        $rawList = isset($json['data']) && is_array($json['data']) ? $json['data'] : $json;

        foreach ($rawList as $item) {
            if (! is_array($item)) continue;

            $date = $item['holiday_date'] ?? $item['date'] ?? $item['tanggal'] ?? null;
            $name = $item['holiday_name'] ?? $item['name'] ?? $item['keterangan'] ?? null;
            $isJoint = (bool) ($item['is_joint_leave'] ?? $item['is_cuti'] ?? $item['cuti_bersama'] ?? false);

            if ($date && $name) {
                $type = $isJoint ? 'joint_leave' : 'national_holiday';
                $normalized[] = [
                    'date' => Carbon::parse($date)->toDateString(),
                    'name' => $name,
                    'type' => $type,
                    'is_joint_leave' => $isJoint,
                    'source' => 'api_online',
                    'source_id' => $item['id'] ?? null,
                ];
            }
        }

        return count($normalized) > 0 ? $normalized : null;
    }

    /**
     * Official SKB 3 Menteri Indonesian National Holidays & Cuti Bersama catalog.
     */
    public function getOfficialCatalogForYear(int $year): array
    {
        if ($year === 2026) {
            return [
                // Libur Nasional 2026
                ['date' => '2026-01-01', 'name' => 'Tahun Baru 2026 Masehi', 'type' => 'national_holiday', 'is_joint_leave' => false],
                ['date' => '2026-01-16', 'name' => "Isra Mi'raj Nabi Muhammad SAW", 'type' => 'national_holiday', 'is_joint_leave' => false],
                ['date' => '2026-02-16', 'name' => 'Cuti Bersama Tahun Baru Imlek 2577 Kongzili', 'type' => 'joint_leave', 'is_joint_leave' => true],
                ['date' => '2026-02-17', 'name' => 'Tahun Baru Imlek 2577 Kongzili', 'type' => 'national_holiday', 'is_joint_leave' => false],
                ['date' => '2026-03-20', 'name' => 'Hari Suci Nyepi Tahun Baru Saka 1948', 'type' => 'national_holiday', 'is_joint_leave' => false],
                ['date' => '2026-03-21', 'name' => 'Hari Raya Idul Fitri 1447 Hijriah (Hari Ke-1)', 'type' => 'national_holiday', 'is_joint_leave' => false],
                ['date' => '2026-03-22', 'name' => 'Hari Raya Idul Fitri 1447 Hijriah (Hari Ke-2)', 'type' => 'national_holiday', 'is_joint_leave' => false],
                ['date' => '2026-03-23', 'name' => 'Cuti Bersama Hari Raya Idul Fitri 1447 Hijriah', 'type' => 'joint_leave', 'is_joint_leave' => true],
                ['date' => '2026-03-24', 'name' => 'Cuti Bersama Hari Raya Idul Fitri 1447 Hijriah', 'type' => 'joint_leave', 'is_joint_leave' => true],
                ['date' => '2026-04-03', 'name' => 'Wafat Yesus Kristus', 'type' => 'national_holiday', 'is_joint_leave' => false],
                ['date' => '2026-05-01', 'name' => 'Hari Buruh Internasional', 'type' => 'national_holiday', 'is_joint_leave' => false],
                ['date' => '2026-05-14', 'name' => 'Kenaikan Yesus Kristus', 'type' => 'national_holiday', 'is_joint_leave' => false],
                ['date' => '2026-05-27', 'name' => 'Hari Raya Idul Adha 1447 Hijriah', 'type' => 'national_holiday', 'is_joint_leave' => false],
                ['date' => '2026-05-31', 'name' => 'Hari Raya Waisak 2570 BE', 'type' => 'national_holiday', 'is_joint_leave' => false],
                ['date' => '2026-06-01', 'name' => 'Hari Lahir Pancasila', 'type' => 'national_holiday', 'is_joint_leave' => false],
                ['date' => '2026-06-16', 'name' => 'Tahun Baru Islam 1448 Hijriah', 'type' => 'national_holiday', 'is_joint_leave' => false],
                ['date' => '2026-08-17', 'name' => 'Hari Proklamasi Kemerdekaan RI', 'type' => 'national_holiday', 'is_joint_leave' => false],
                ['date' => '2026-08-25', 'name' => 'Maulid Nabi Muhammad SAW', 'type' => 'national_holiday', 'is_joint_leave' => false],
                ['date' => '2026-12-25', 'name' => 'Hari Raya Natal', 'type' => 'national_holiday', 'is_joint_leave' => false],
                ['date' => '2026-12-26', 'name' => 'Cuti Bersama Hari Raya Natal', 'type' => 'joint_leave', 'is_joint_leave' => true],
            ];
        }

        // Generic fallback for any other year
        return [
            ['date' => "{$year}-01-01", 'name' => "Tahun Baru {$year} Masehi", 'type' => 'national_holiday', 'is_joint_leave' => false],
            ['date' => "{$year}-05-01", 'name' => 'Hari Buruh Internasional', 'type' => 'national_holiday', 'is_joint_leave' => false],
            ['date' => "{$year}-06-01", 'name' => 'Hari Lahir Pancasila', 'type' => 'national_holiday', 'is_joint_leave' => false],
            ['date' => "{$year}-08-17", 'name' => 'Hari Kemerdekaan Republik Indonesia', 'type' => 'national_holiday', 'is_joint_leave' => false],
            ['date' => "{$year}-12-25", 'name' => 'Hari Raya Natal', 'type' => 'national_holiday', 'is_joint_leave' => false],
        ];
    }
}
