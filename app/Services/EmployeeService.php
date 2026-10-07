<?php

namespace App\Services;

use App\Models\Department;
use App\Models\Employee;
use App\Support\PhoneNumberNormalizer;
use DomainException;
use Illuminate\Support\Facades\DB;

class EmployeeService
{
    public function __construct(
        protected AuditLogService $auditLogService
    ) {}

    /**
     * Create a new employee with normalized contact information and audit log.
     */
    public function createEmployee(array $data): Employee
    {
        return DB::transaction(function () use ($data) {
            if (! empty($data['phone'])) {
                $data['phone'] = PhoneNumberNormalizer::normalize($data['phone']);
            }

            if (! empty($data['department_id'])) {
                $dept = Department::find($data['department_id']);
                if ($dept) {
                    $data['department'] = $dept->name;
                }
            }

            $employee = Employee::create([
                'employee_number' => trim($data['employee_number']),
                'full_name' => trim($data['full_name']),
                'office_id' => $data['office_id'] ?? null,
                'department_id' => $data['department_id'] ?? null,
                'division_id' => $data['division_id'] ?? null,
                'position_id' => $data['position_id'] ?? null,
                'department' => ! empty($data['department']) ? trim($data['department']) : null,
                'position' => ! empty($data['position']) ? trim($data['position']) : null,
                'email' => ! empty($data['email']) ? strtolower(trim($data['email'])) : null,
                'phone' => $data['phone'] ?? null,
                'hire_date' => $data['hire_date'] ?? null,
                'employment_status' => $data['employment_status'] ?? 'active',
                'gaji_pokok' => $data['gaji_pokok'] ?? 0,
                'tunjangan_kehadiran' => $data['tunjangan_kehadiran'] ?? 0,
                'tunjangan_keluarga' => $data['tunjangan_keluarga'] ?? 0,
                'tunjangan_jabatan' => $data['tunjangan_jabatan'] ?? 0,
                'uang_makan_per_hari' => $data['uang_makan_per_hari'] ?? 0,
                'uang_transportasi_per_hari' => $data['uang_transportasi_per_hari'] ?? 0,
                'bpjs_ketenagakerjaan_enabled' => (bool) ($data['bpjs_ketenagakerjaan_enabled'] ?? false),
                'bpjs_ketenagakerjaan_amount' => $data['bpjs_ketenagakerjaan_amount'] ?? 0,
                'default_overtime_rate' => $data['default_overtime_rate'] ?? 0,
            ]);

            $this->auditLogService->log(
                action: 'employee.created',
                subject: $employee,
                changes: [
                    'employee_number' => $employee->employee_number,
                    'full_name' => $employee->full_name,
                    'office_id' => $employee->office_id,
                    'department_id' => $employee->department_id,
                    'division_id' => $employee->division_id,
                    'position_id' => $employee->position_id,
                    'department' => $employee->department,
                    'position' => $employee->position,
                    'email' => $employee->email,
                    'phone' => $employee->phone,
                    'employment_status' => $employee->employment_status,
                    'gaji_pokok' => $employee->gaji_pokok,
                    'tunjangan_kehadiran' => $employee->tunjangan_kehadiran,
                    'tunjangan_keluarga' => $employee->tunjangan_keluarga,
                    'tunjangan_jabatan' => $employee->tunjangan_jabatan,
                    'uang_makan_per_hari' => $employee->uang_makan_per_hari,
                    'uang_transportasi_per_hari' => $employee->uang_transportasi_per_hari,
                    'bpjs_ketenagakerjaan_enabled' => $employee->bpjs_ketenagakerjaan_enabled,
                    'bpjs_ketenagakerjaan_amount' => $employee->bpjs_ketenagakerjaan_amount,
                    'default_overtime_rate' => $employee->default_overtime_rate,
                ]
            );

            return $employee;
        });
    }

    /**
     * Update an existing employee with normalized contact and audit trail.
     */
    public function updateEmployee(Employee $employee, array $data): Employee
    {
        return DB::transaction(function () use ($employee, $data) {
            $original = $employee->only([
                'employee_number',
                'full_name',
                'office_id',
                'department_id',
                'division_id',
                'position_id',
                'department',
                'position',
                'email',
                'phone',
                'hire_date',
                'employment_status',
                'gaji_pokok',
                'tunjangan_kehadiran',
                'tunjangan_keluarga',
                'tunjangan_jabatan',
                'uang_makan_per_hari',
                'uang_transportasi_per_hari',
                'bpjs_ketenagakerjaan_enabled',
                'bpjs_ketenagakerjaan_amount',
                'default_overtime_rate',
            ]);

            if (array_key_exists('department_id', $data) && ! empty($data['department_id'])) {
                $dept = Department::find($data['department_id']);
                if ($dept) {
                    $data['department'] = $dept->name;
                }
            }

            if (array_key_exists('phone', $data)) {
                $data['phone'] = ! empty($data['phone'])
                    ? PhoneNumberNormalizer::normalize($data['phone'])
                    : null;
            }

            if (array_key_exists('email', $data)) {
                $data['email'] = ! empty($data['email'])
                    ? strtolower(trim($data['email']))
                    : null;
            }

            if (array_key_exists('employee_number', $data)) {
                $data['employee_number'] = trim($data['employee_number']);
            }

            if (array_key_exists('full_name', $data)) {
                $data['full_name'] = trim($data['full_name']);
            }

            $employee->fill($data)->save();

            $changes = [];
            foreach ($data as $key => $newValue) {
                $oldValue = $original[$key] ?? null;
                // If date comparison
                if ($oldValue instanceof \DateTimeInterface) {
                    $oldValue = $oldValue->format('Y-m-d');
                }
                if ($oldValue != $newValue) {
                    $changes[$key] = [
                        'from' => $oldValue,
                        'to' => $newValue,
                    ];
                }
            }

            if (! empty($changes)) {
                $this->auditLogService->log(
                    action: 'employee.updated',
                    subject: $employee,
                    changes: $changes
                );
            }

            return $employee;
        });
    }

    /**
     * Toggle active/inactive status of an employee.
     */
    public function toggleStatus(Employee $employee): Employee
    {
        return DB::transaction(function () use ($employee) {
            $previousStatus = $employee->employment_status;
            $newStatus = $previousStatus === 'active' ? 'inactive' : 'active';

            $employee->employment_status = $newStatus;
            $employee->save();

            $this->auditLogService->log(
                action: 'employee.status_toggled',
                subject: $employee,
                changes: [
                    'previous_status' => $previousStatus,
                    'new_status' => $newStatus,
                ]
            );

            return $employee;
        });
    }

    /**
     * Delete an employee.
     * Rejects physical deletion if the employee has payslip history to maintain audit trail integrity.
     */
    public function deleteEmployee(Employee $employee): void
    {
        if ($employee->hasPayslipHistory()) {
            throw new DomainException(
                'Karyawan memiliki riwayat slip gaji sehingga tidak dapat dihapus secara fisik. Silakan nonaktifkan status karyawan untuk menjaga integritas data historis.'
            );
        }

        DB::transaction(function () use ($employee) {
            $deletedData = [
                'employee_number' => $employee->employee_number,
                'full_name' => $employee->full_name,
                'department' => $employee->department,
            ];

            $employee->delete();

            $this->auditLogService->log(
                action: 'employee.deleted',
                subject: 'App\Models\Employee',
                changes: $deletedData
            );
        });
    }
}
