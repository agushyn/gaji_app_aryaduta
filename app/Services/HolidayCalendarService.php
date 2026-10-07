<?php

namespace App\Services;

use App\Models\HolidayCalendar;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

class HolidayCalendarService
{
    /**
     * Get paginated holidays with optional filters.
     */
    public function getPaginatedHolidays(array $filters = [], int $perPage = 25): LengthAwarePaginator
    {
        $query = HolidayCalendar::query()->orderBy('date');

        if (! empty($filters['year'])) {
            $query->where('year', (int) $filters['year']);
        }

        if (! empty($filters['type'])) {
            $query->where('type', $filters['type']);
        }

        if (isset($filters['is_active']) && $filters['is_active'] !== '') {
            $query->where('is_active', (bool) $filters['is_active']);
        }

        if (! empty($filters['search'])) {
            $term = trim($filters['search']);
            $query->where(function ($q) use ($term) {
                $q->where('name', 'like', "%{$term}%")
                    ->orWhere('date', 'like', "%{$term}%");
            });
        }

        return $query->paginate($perPage)->withQueryString();
    }

    /**
     * Get all active holidays for a given year.
     *
     * @return Collection<int, HolidayCalendar>
     */
    public function getActiveHolidaysForYear(int $year): Collection
    {
        return HolidayCalendar::active()
            ->forYear($year)
            ->orderBy('date')
            ->get();
    }

    /**
     * Get holidays between two dates.
     *
     * @return Collection<int, HolidayCalendar>
     */
    public function getHolidaysBetween(string|Carbon $startDate, string|Carbon $endDate): Collection
    {
        $start = is_string($startDate) ? Carbon::parse($startDate)->toDateString() : $startDate->toDateString();
        $end = is_string($endDate) ? Carbon::parse($endDate)->toDateString() : $endDate->toDateString();

        return HolidayCalendar::active()
            ->whereBetween('date', [$start, $end])
            ->orderBy('date')
            ->get();
    }

    /**
     * Find a holiday by date.
     */
    public function findByDate(string|Carbon $date): ?HolidayCalendar
    {
        $dateStr = is_string($date) ? Carbon::parse($date)->toDateString() : $date->toDateString();

        return HolidayCalendar::active()
            ->where('date', $dateStr)
            ->first();
    }

    /**
     * Create a manual holiday record.
     */
    public function createHoliday(array $data): HolidayCalendar
    {
        $date = Carbon::parse($data['date']);
        $year = (int) ($data['year'] ?? $date->year);
        $type = $data['type'] ?? 'national_holiday';
        $isJointLeave = (bool) ($data['is_joint_leave'] ?? ($type === 'joint_leave'));
        $isPayrollHoliday = isset($data['is_payroll_holiday'])
            ? (bool) $data['is_payroll_holiday']
            : ($type === 'national_holiday');

        return HolidayCalendar::create([
            'date' => $date->toDateString(),
            'name' => trim($data['name']),
            'type' => $type,
            'is_joint_leave' => $isJointLeave,
            'is_payroll_holiday' => $isPayrollHoliday,
            'year' => $year,
            'source' => $data['source'] ?? 'manual',
            'source_id' => $data['source_id'] ?? null,
            'is_active' => $data['is_active'] ?? true,
        ]);
    }

    /**
     * Update an existing holiday.
     */
    public function updateHoliday(HolidayCalendar $holiday, array $data): HolidayCalendar
    {
        $date = isset($data['date']) ? Carbon::parse($data['date']) : $holiday->date;
        $type = $data['type'] ?? $holiday->type;
        $isJointLeave = isset($data['is_joint_leave']) ? (bool) $data['is_joint_leave'] : ($type === 'joint_leave');

        $holiday->update([
            'date' => $date->toDateString(),
            'name' => isset($data['name']) ? trim($data['name']) : $holiday->name,
            'type' => $type,
            'is_joint_leave' => $isJointLeave,
            'is_payroll_holiday' => isset($data['is_payroll_holiday']) ? (bool) $data['is_payroll_holiday'] : $holiday->is_payroll_holiday,
            'year' => (int) ($data['year'] ?? $date->year),
            'source' => $data['source'] ?? $holiday->source,
            'source_id' => $data['source_id'] ?? $holiday->source_id,
            'is_active' => isset($data['is_active']) ? (bool) $data['is_active'] : $holiday->is_active,
        ]);

        return $holiday->fresh();
    }

    /**
     * Delete a holiday.
     */
    public function deleteHoliday(HolidayCalendar $holiday): void
    {
        $holiday->delete();
    }

    /**
     * Toggle active status.
     */
    public function toggleStatus(HolidayCalendar $holiday): HolidayCalendar
    {
        $holiday->is_active = ! $holiday->is_active;
        $holiday->save();

        return $holiday;
    }

    /**
     * Toggle whether this holiday counts as a paid holiday for payroll.
     */
    public function togglePayrollHoliday(HolidayCalendar $holiday): HolidayCalendar
    {
        $holiday->is_payroll_holiday = ! $holiday->is_payroll_holiday;
        $holiday->save();

        return $holiday;
    }
}
