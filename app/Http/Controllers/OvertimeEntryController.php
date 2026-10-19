<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreOvertimeEntryRequest;
use App\Http\Requests\UpdateOvertimeEntryRequest;
use App\Models\Employee;
use App\Models\OvertimeEntry;
use App\Models\PayrollPeriod;
use App\Services\AuditLogService;
use App\Services\OvertimeCalculationService;
use App\Services\PayrollDayTypeService;
use App\Services\PayslipService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

class OvertimeEntryController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        protected PayslipService $payslipService,
        protected OvertimeCalculationService $overtimeService,
        protected PayrollDayTypeService $dayTypeService,
        protected AuditLogService $auditLogService
    ) {}

    /**
     * Store a newly created overtime entry in storage and sync employee's payslip.
     */
    public function store(StoreOvertimeEntryRequest $request, PayrollPeriod $payrollPeriod): RedirectResponse
    {
        $duration = OvertimeEntry::calculateDuration(
            (string) $request->input('start_time'),
            (string) $request->input('end_time')
        );

        $entry = OvertimeEntry::create([
            'payroll_period_id' => $payrollPeriod->id,
            'employee_id' => $request->input('employee_id'),
            'date' => $request->input('date'),
            'day_type' => $request->input('day_type'),
            'start_time' => $request->input('start_time'),
            'end_time' => $request->input('end_time'),
            'duration_minutes' => $duration['minutes'],
            'duration_hours' => $duration['hours'],
            'note' => $request->input('note'),
            'created_by' => $request->user()?->id,
            'updated_by' => $request->user()?->id,
        ]);

        // Sync overtime to draft payslip if exists
        $this->payslipService->syncEmployeeOvertime($payrollPeriod, $entry->employee_id);

        $employee = Employee::find($entry->employee_id);

        $this->auditLogService->log(
            action: 'overtime.created',
            subject: $entry,
            changes: [
                'period_code' => $payrollPeriod->period_code,
                'employee_number' => $employee?->employee_number,
                'employee_name' => $employee?->full_name,
                'date' => $entry->date?->format('Y-m-d'),
                'day_type' => $entry->day_type,
                'start_time' => $entry->start_time,
                'end_time' => $entry->end_time,
                'duration_minutes' => $entry->duration_minutes,
                'duration_hours' => $entry->duration_hours,
            ]
        );

        return redirect()
            ->back()
            ->with('success', "Data lembur untuk {$employee?->full_name} ({$duration['formatted']}) berhasil ditambahkan.");
    }

    /**
     * Update the specified overtime entry in storage and sync employee's payslip.
     */
    public function update(UpdateOvertimeEntryRequest $request, PayrollPeriod $payrollPeriod, OvertimeEntry $overtimeEntry): RedirectResponse
    {
        if ($overtimeEntry->payroll_period_id !== $payrollPeriod->id) {
            abort(404, 'Data lembur tidak ditemukan pada periode ini.');
        }

        $duration = OvertimeEntry::calculateDuration(
            (string) $request->input('start_time'),
            (string) $request->input('end_time')
        );

        $oldEmployeeId = $overtimeEntry->employee_id;
        $newEmployeeId = (int) $request->input('employee_id');

        $overtimeEntry->update([
            'employee_id' => $newEmployeeId,
            'date' => $request->input('date'),
            'day_type' => $request->input('day_type'),
            'start_time' => $request->input('start_time'),
            'end_time' => $request->input('end_time'),
            'duration_minutes' => $duration['minutes'],
            'duration_hours' => $duration['hours'],
            'note' => $request->input('note'),
            'updated_by' => $request->user()?->id,
        ]);

        // Sync overtime for updated employee
        $this->payslipService->syncEmployeeOvertime($payrollPeriod, $newEmployeeId);

        // If employee was changed, also re-sync previous employee
        if ($oldEmployeeId !== $newEmployeeId) {
            $this->payslipService->syncEmployeeOvertime($payrollPeriod, $oldEmployeeId);
        }

        $employee = Employee::find($newEmployeeId);

        $this->auditLogService->log(
            action: 'overtime.updated',
            subject: $overtimeEntry,
            changes: [
                'period_code' => $payrollPeriod->period_code,
                'employee_name' => $employee?->full_name,
                'date' => $overtimeEntry->date?->format('Y-m-d'),
                'day_type' => $overtimeEntry->day_type,
                'duration_hours' => $overtimeEntry->duration_hours,
            ]
        );

        return redirect()
            ->back()
            ->with('success', "Data lembur untuk {$employee?->full_name} ({$duration['formatted']}) berhasil diperbarui.");
    }

    /**
     * Remove the specified overtime entry from storage and sync employee's payslip.
     */
    public function destroy(Request $request, PayrollPeriod $payrollPeriod, OvertimeEntry $overtimeEntry): RedirectResponse
    {
        $this->authorize('update', $payrollPeriod);

        if (! $payrollPeriod->canBeModified()) {
            abort(403, 'Data lembur tidak dapat dihapus karena periode gaji telah dikunci.');
        }

        if ($overtimeEntry->payroll_period_id !== $payrollPeriod->id) {
            abort(404, 'Data lembur tidak ditemukan pada periode ini.');
        }

        $employeeId = $overtimeEntry->employee_id;
        $employee = Employee::find($employeeId);
        $dateStr = $overtimeEntry->date?->format('d/m/Y');
        $durationHours = $overtimeEntry->duration_hours;

        $overtimeEntry->delete();

        // Re-sync payslip for this employee
        $this->payslipService->syncEmployeeOvertime($payrollPeriod, $employeeId);

        $this->auditLogService->log(
            action: 'overtime.deleted',
            subject: OvertimeEntry::class,
            changes: [
                'period_code' => $payrollPeriod->period_code,
                'employee_name' => $employee?->full_name,
                'date' => $dateStr,
                'duration_hours' => $durationHours,
            ]
        );

        return redirect()
            ->back()
            ->with('success', "Data lembur tanggal {$dateStr} untuk {$employee?->full_name} berhasil dihapus.");
    }

    /**
     * AJAX/JSON live calculation preview for the frontend.
     */
    public function previewCalculation(Request $request, PayrollPeriod $payrollPeriod): JsonResponse
    {
        $request->validate([
            'employee_id' => 'required|exists:employees,id',
            'date' => 'required|date_format:Y-m-d',
            'day_type' => 'nullable|in:working_day,holiday',
            'start_time' => 'required|string',
            'end_time' => 'required|string',
        ]);

        $employee = Employee::findOrFail($request->input('employee_id'));
        $date = $request->input('date');

        // Suggest day type if not explicitly set
        $dayType = $request->input('day_type');
        if (! $dayType) {
            $dayType = $this->dayTypeService->isOvertimeHoliday($date) ? 'holiday' : 'working_day';
        }

        try {
            $duration = OvertimeEntry::calculateDuration(
                (string) $request->input('start_time'),
                (string) $request->input('end_time')
            );
        } catch (InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }

        // Run calculation simulation
        $isHoliday = $dayType === 'holiday';
        $params = [
            'rate' => $employee->default_overtime_rate,
            $isHoliday ? 'holiday_minutes' : 'working_day_minutes' => $duration['minutes'],
            $isHoliday ? 'holiday_days' : 'working_day_days' => 1,
        ];

        $calc = $this->overtimeService->calculateForEmployee($employee, $params);
        $snap = $calc['snapshot'][$isHoliday ? 'holiday' : 'working_day'];

        return response()->json([
            'success' => true,
            'duration_minutes' => $duration['minutes'],
            'duration_hours' => $duration['hours'],
            'duration_formatted' => $duration['formatted'],
            'day_type' => $dayType,
            'is_holiday' => $isHoliday,
            'employee_rate' => (float) $calc['rate'],
            'employee_rate_formatted' => 'Rp ' . number_format((float) $calc['rate'], 0, ',', '.'),
            'threshold' => (float) $snap['break_threshold'],
            'threshold_reached' => ((float) $snap['break_deduction'] > 0),
            'break_deduction_hours' => (float) $snap['break_deduction'],
            'net_hours' => (float) $snap['net_hours'],
            'estimated_pay' => (float) $calc['amount'],
            'estimated_pay_formatted' => 'Rp ' . number_format((float) $calc['amount'], 0, ',', '.'),
            'trace' => $calc['trace'],
        ]);
    }
}
