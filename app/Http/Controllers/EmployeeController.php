<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreEmployeeRequest;
use App\Http\Requests\UpdateEmployeeRequest;
use App\Models\AuditLog;
use App\Models\Department;
use App\Models\Employee;
use App\Services\EmployeeService;
use DomainException;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class EmployeeController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        protected EmployeeService $employeeService
    ) {}

    /**
     * Display a listing of the employees with search and filters.
     */
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Employee::class);

        $query = Employee::with(['office', 'department', 'division', 'position']);

        if ($request->filled('search')) {
            $query->search($request->string('search')->trim());
        }

        if ($request->filled('office_id') && $request->input('office_id') !== 'all') {
            $query->filterOffice((int) $request->input('office_id'));
        }

        if ($request->filled('department')) {
            $query->filterDepartment($request->string('department'));
        }

        if ($request->filled('division')) {
            $query->filterDivision($request->string('division'));
        }

        if ($request->filled('position_id') && $request->input('position_id') !== 'all') {
            $query->filterPosition((int) $request->input('position_id'));
        }

        if ($request->filled('status')) {
            $query->filterStatus($request->string('status'));
        }

        $employees = $query->latest()->paginate(15)->withQueryString();

        $offices = \App\Models\Office::with(['divisions' => function ($q) {
            $q->active()->orderBy('name');
        }])->active()->orderBy('name')->get(['id', 'name', 'code']);

        $positions = \App\Models\Position::active()->orderBy('name')->get(['id', 'name', 'code']);

        $departments = Department::with(['divisions' => function ($q) {
            $q->active()->orderBy('name');
        }])->active()->orderBy('name')->get(['id', 'name', 'code']);

        $counts = [
            'total' => Employee::count(),
            'active' => Employee::where('employment_status', 'active')->count(),
            'inactive' => Employee::where('employment_status', 'inactive')->count(),
        ];

        return Inertia::render('Employees/Index', [
            'employees' => $employees,
            'offices' => $offices,
            'positions' => $positions,
            'departments' => $departments,
            'counts' => $counts,
            'filters' => $request->only(['search', 'office_id', 'department', 'division', 'position_id', 'status', 'view_mode']),
        ]);
    }

    /**
     * Show the form for creating a new employee.
     */
    public function create(): Response
    {
        $this->authorize('create', Employee::class);

        $offices = \App\Models\Office::with(['divisions' => function ($q) {
            $q->active()->orderBy('name');
        }])->active()->orderBy('name')->get(['id', 'name', 'code']);

        $positions = \App\Models\Position::active()->orderBy('name')->get(['id', 'name', 'code']);

        $departments = Department::with(['divisions' => function ($q) {
            $q->active()->orderBy('name');
        }])->active()->orderBy('name')->get(['id', 'name', 'code']);

        return Inertia::render('Employees/Create', [
            'offices' => $offices,
            'positions' => $positions,
            'departments' => $departments,
        ]);
    }

    /**
     * Store a newly created employee in storage.
     */
    public function store(StoreEmployeeRequest $request): RedirectResponse
    {
        $employee = $this->employeeService->createEmployee($request->validated());

        return redirect()
            ->route('employees.index')
            ->with('success', "Karyawan {$employee->full_name} ({$employee->employee_number}) berhasil ditambahkan.");
    }

    /**
     * Display the specified employee details and audit history.
     */
    public function show(Employee $employee): Response
    {
        $this->authorize('view', $employee);

        $employee->load(['office', 'department', 'division', 'position']);

        $auditLogs = AuditLog::with('user:id,name,email')
            ->where('subject_type', Employee::class)
            ->where('subject_id', (string) $employee->id)
            ->latest('created_at')
            ->take(15)
            ->get();

        return Inertia::render('Employees/Show', [
            'employee' => $employee,
            'auditLogs' => $auditLogs,
        ]);
    }

    /**
     * Show the form for editing the specified employee.
     */
    public function edit(Employee $employee): Response
    {
        $this->authorize('update', $employee);

        $employee->load(['office', 'department', 'division', 'position']);

        $offices = \App\Models\Office::with(['divisions' => function ($q) {
            $q->active()->orderBy('name');
        }])->active()->orderBy('name')->get(['id', 'name', 'code']);

        $positions = \App\Models\Position::active()->orderBy('name')->get(['id', 'name', 'code']);

        $departments = Department::with(['divisions' => function ($q) {
            $q->active()->orderBy('name');
        }])->active()->orderBy('name')->get(['id', 'name', 'code']);

        return Inertia::render('Employees/Edit', [
            'employee' => $employee,
            'offices' => $offices,
            'positions' => $positions,
            'departments' => $departments,
        ]);
    }

    /**
     * Update the specified employee in storage.
     */
    public function update(UpdateEmployeeRequest $request, Employee $employee): RedirectResponse
    {
        $this->employeeService->updateEmployee($employee, $request->validated());

        return redirect()
            ->route('employees.show', $employee)
            ->with('success', "Data karyawan {$employee->full_name} berhasil diperbarui.");
    }

    /**
     * Remove the specified employee from storage.
     */
    public function destroy(Employee $employee): RedirectResponse
    {
        $this->authorize('delete', $employee);

        try {
            $name = $employee->full_name;
            $this->employeeService->deleteEmployee($employee);

            return redirect()
                ->route('employees.index')
                ->with('success', "Karyawan {$name} berhasil dihapus.");
        } catch (DomainException $e) {
            return redirect()
                ->back()
                ->with('error', $e->getMessage());
        }
    }

    /**
     * Toggle active/inactive status of an employee.
     */
    public function toggleStatus(Employee $employee): RedirectResponse
    {
        $this->authorize('update', $employee);

        $updated = $this->employeeService->toggleStatus($employee);
        $statusText = $updated->is_active ? 'diaktifkan' : 'dinonaktifkan';

        return redirect()
            ->back()
            ->with('success', "Status karyawan {$updated->full_name} berhasil {$statusText}.");
    }
}
