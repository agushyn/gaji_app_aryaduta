<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreHolidayCalendarRequest;
use App\Http\Requests\UpdateHolidayCalendarRequest;
use App\Models\CompanySetting;
use App\Models\HolidayCalendar;
use App\Services\HolidayCalendarService;
use App\Services\HolidaySyncService;
use Carbon\Carbon;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class HolidayCalendarController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        protected HolidayCalendarService $holidayService,
        protected HolidaySyncService $syncService
    ) {}

    /**
     * Display a listing of holiday calendars.
     */
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', HolidayCalendar::class);

        $selectedYear = $request->filled('year') ? (int) $request->input('year') : Carbon::now()->year;

        $query = HolidayCalendar::query()
            ->where('year', $selectedYear);

        if ($request->filled('search')) {
            $search = $request->string('search')->trim();
            $query->where('name', 'like', "%{$search}%");
        }

        if ($request->filled('type')) {
            $query->where('type', $request->string('type'));
        }

        if ($request->filled('payroll_holiday')) {
            $isPayrollHoliday = $request->string('payroll_holiday') === 'yes';
            $query->where('is_payroll_holiday', $isPayrollHoliday);
        }

        $holidays = $query->orderBy('date')->paginate(30)->withQueryString();

        $holidays->getCollection()->transform(function ($h) {
            $dateCarbon = Carbon::parse($h->date);
            return [
                'id' => $h->id,
                'date' => $h->date->format('Y-m-d'),
                'formatted_date' => $dateCarbon->translatedFormat('d F Y'),
                'day_name' => $dateCarbon->translatedFormat('l'),
                'name' => $h->name,
                'type' => $h->type,
                'type_label' => match ($h->type) {
                    'national_holiday' => 'Libur Nasional',
                    'joint_leave' => 'Cuti Bersama',
                    default => 'Libur Khusus',
                },
                'is_joint_leave' => (bool) $h->is_joint_leave,
                'is_payroll_holiday' => (bool) $h->is_payroll_holiday,
                'is_active' => (bool) $h->is_active,
                'source' => $h->source,
                'description' => $h->description,
            ];
        });

        // Available years in database or range +/- 2 years
        $availableYears = HolidayCalendar::distinct()->pluck('year')->sort()->values()->toArray();
        if (empty($availableYears)) {
            $availableYears = [$selectedYear - 1, $selectedYear, $selectedYear + 1];
        }

        $lastSyncedAt = CompanySetting::get("holiday_calendar_{$selectedYear}_last_synced_at");
        $lastSyncedFormatted = $lastSyncedAt ? Carbon::parse($lastSyncedAt)->translatedFormat('d F Y H:i') : null;

        $counts = [
            'total' => HolidayCalendar::where('year', $selectedYear)->count(),
            'national' => HolidayCalendar::where('year', $selectedYear)->where('type', 'national_holiday')->count(),
            'joint_leave' => HolidayCalendar::where('year', $selectedYear)->where('type', 'joint_leave')->count(),
            'payroll_holiday' => HolidayCalendar::where('year', $selectedYear)->where('is_payroll_holiday', true)->count(),
        ];

        return Inertia::render('HolidayCalendars/Index', [
            'holidays' => $holidays,
            'selectedYear' => $selectedYear,
            'availableYears' => $availableYears,
            'counts' => $counts,
            'lastSyncedAt' => $lastSyncedFormatted,
            'filters' => $request->only(['search', 'type', 'payroll_holiday', 'year']),
        ]);
    }

    /**
     * Store a manually created holiday entry.
     */
    public function store(StoreHolidayCalendarRequest $request): RedirectResponse
    {
        $this->authorize('create', HolidayCalendar::class);

        $holiday = $this->holidayService->createHoliday($request->validated());

        return redirect()
            ->back()
            ->with('success', "Hari libur '{$holiday->name}' berhasil ditambahkan ke kalender.");
    }

    /**
     * Update holiday entry.
     */
    public function update(StoreHolidayCalendarRequest $request, HolidayCalendar $holidayCalendar): RedirectResponse
    {
        $this->authorize('update', $holidayCalendar);

        $updated = $this->holidayService->updateHoliday($holidayCalendar, $request->validated());

        return redirect()
            ->back()
            ->with('success', "Hari libur '{$updated->name}' berhasil diperbarui.");
    }

    /**
     * Delete holiday entry.
     */
    public function destroy(HolidayCalendar $holidayCalendar): RedirectResponse
    {
        $this->authorize('delete', $holidayCalendar);

        $name = $holidayCalendar->name;
        $this->holidayService->deleteHoliday($holidayCalendar);

        return redirect()
            ->back()
            ->with('success', "Hari libur '{$name}' berhasil dihapus dari kalender.");
    }

    /**
     * Toggle whether this day counts as a payroll holiday.
     */
    public function togglePayrollHoliday(HolidayCalendar $holidayCalendar): RedirectResponse
    {
        $this->authorize('update', $holidayCalendar);

        $updated = $this->holidayService->togglePayrollHoliday($holidayCalendar);
        $statusText = $updated->is_payroll_holiday
            ? 'diaktifkan sebagai hari libur payroll'
            : 'dikecualikan dari hari libur payroll';

        return redirect()
            ->back()
            ->with('success', "'{$updated->name}' berhasil {$statusText}.");
    }

    /**
     * Toggle active status.
     */
    public function toggleStatus(HolidayCalendar $holidayCalendar): RedirectResponse
    {
        $this->authorize('update', $holidayCalendar);

        $updated = $this->holidayService->toggleActive($holidayCalendar);
        $statusText = $updated->is_active ? 'diaktifkan' : 'dinonaktifkan';

        return redirect()
            ->back()
            ->with('success', "'{$updated->name}' berhasil {$statusText}.");
    }

    /**
     * Sync holidays for a given year.
     */
    public function sync(Request $request): RedirectResponse
    {
        $this->authorize('sync', HolidayCalendar::class);

        $year = (int) ($request->input('year') ?? Carbon::now()->year);

        try {
            $result = $this->syncService->syncYear($year);

            return redirect()
                ->route('holiday-calendars.index', ['year' => $year])
                ->with('success', "Sinkronisasi kalender tahun {$year} berhasil: {$result['synced']} hari libur diperbarui ({$result['source']}).");
        } catch (Throwable $e) {
            return redirect()
                ->back()
                ->with('error', "Gagal melakukan sinkronisasi kalender: {$e->getMessage()}");
        }
    }
}
