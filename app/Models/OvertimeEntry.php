<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use InvalidArgumentException;

class OvertimeEntry extends Model
{
    use HasFactory;

    protected $fillable = [
        'payroll_period_id',
        'employee_id',
        'date',
        'day_type',
        'start_time',
        'end_time',
        'duration_minutes',
        'duration_hours',
        'note',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date:Y-m-d',
            'duration_minutes' => 'integer',
            'duration_hours' => 'float',
        ];
    }

    /**
     * Relationship with PayrollPeriod.
     */
    public function payrollPeriod(): BelongsTo
    {
        return $this->belongsTo(PayrollPeriod::class);
    }

    /**
     * Relationship with Employee.
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * User who created this entry.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * User who last updated this entry.
     */
    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * Calculate exact duration from start and end times supporting midnight crossing.
     *
     * @param  string  $startTime Format "HH:MM" or "HH:MM:SS"
     * @param  string  $endTime   Format "HH:MM" or "HH:MM:SS"
     * @return array{minutes: int, hours: float, formatted: string}
     */
    public static function calculateDuration(string $startTime, string $endTime): array
    {
        $startTime = trim($startTime);
        $endTime = trim($endTime);

        if (! preg_match('/^(\d{1,2}):(\d{2})(?::\d{2})?$/', $startTime, $startMatches)) {
            throw new InvalidArgumentException("Format jam mulai tidak valid: {$startTime}");
        }
        if (! preg_match('/^(\d{1,2}):(\d{2})(?::\d{2})?$/', $endTime, $endMatches)) {
            throw new InvalidArgumentException("Format jam selesai tidak valid: {$endTime}");
        }

        $startH = (int) $startMatches[1];
        $startM = (int) $startMatches[2];
        $endH = (int) $endMatches[1];
        $endM = (int) $endMatches[2];

        if ($startH < 0 || $startH > 23 || $startM < 0 || $startM > 59) {
            throw new InvalidArgumentException("Jam mulai berada di luar rentang valid: {$startTime}");
        }
        if ($endH < 0 || $endH > 23 || $endM < 0 || $endM > 59) {
            throw new InvalidArgumentException("Jam selesai berada di luar rentang valid: {$endTime}");
        }

        $startMinutes = ($startH * 60) + $startM;
        $endMinutes = ($endH * 60) + $endM;

        if ($startMinutes === $endMinutes) {
            throw new InvalidArgumentException('Jam selesai tidak boleh sama dengan jam mulai (durasi 0 menit).');
        }

        // Support crossing midnight (e.g. 22:00 to 02:00 -> (120 + 1440) - 1320 = 240 mins = 4 hours)
        if ($endMinutes < $startMinutes) {
            $durationMinutes = ($endMinutes + 1440) - $startMinutes;
        } else {
            $durationMinutes = $endMinutes - $startMinutes;
        }

        $hours = round($durationMinutes / 60, 4);

        $wholeHours = intdiv($durationMinutes, 60);
        $remMinutes = $durationMinutes % 60;

        $formatted = $remMinutes > 0
            ? sprintf('%d jam %d menit', $wholeHours, $remMinutes)
            : sprintf('%d jam', $wholeHours);

        return [
            'minutes' => $durationMinutes,
            'hours' => $hours,
            'formatted' => $formatted,
        ];
    }
}
