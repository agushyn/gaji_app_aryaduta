<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreDepartmentRequest;
use App\Http\Requests\UpdateDepartmentRequest;
use App\Models\Department;
use App\Services\AuditLogService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DepartmentController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        protected AuditLogService $auditLogService
    ) {}

    /**
     * Display a listing of departments with search and counts.
     */
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Department::class);

        $query = Department::query()->withCount(['divisions', 'employees']);

        if ($request->filled('search')) {
            $query->search($request->string('search')->trim());
        }

        if ($request->filled('status') && $request->input('status') !== 'all') {
            $query->where('is_active', $request->input('status') === 'active');
        }

        $departments = $query->orderBy('name')->paginate(10)->withQueryString();

        $counts = [
            'total' => Department::count(),
            'active' => Department::where('is_active', true)->count(),
            'inactive' => Department::where('is_active', false)->count(),
        ];

        return Inertia::render('Departments/Index', [
            'departments' => $departments,
            'counts' => $counts,
            'filters' => $request->only(['search', 'status']),
        ]);
    }

    /**
     * Store a newly created department.
     */
    public function store(StoreDepartmentRequest $request): RedirectResponse
    {
        $this->authorize('create', Department::class);

        $department = Department::create($request->validated());

        $this->auditLogService->log(
            action: 'department.created',
            subject: $department,
            changes: [
                'name' => $department->name,
                'code' => $department->code,
                'description' => $department->description,
                'is_active' => $department->is_active,
            ]
        );

        return redirect()
            ->route('master.departments.index')
            ->with('success', "Departemen {$department->name} ({$department->code}) berhasil ditambahkan.");
    }

    /**
     * Update the specified department.
     */
    public function update(UpdateDepartmentRequest $request, Department $department): RedirectResponse
    {
        $this->authorize('update', $department);

        $oldData = $department->only(['name', 'code', 'description', 'is_active']);
        $department->update($request->validated());

        $changes = [];
        foreach ($request->validated() as $key => $val) {
            if (($oldData[$key] ?? null) != $val) {
                $changes[$key] = [
                    'from' => $oldData[$key] ?? null,
                    'to' => $val,
                ];
            }
        }

        if (! empty($changes)) {
            $this->auditLogService->log(
                action: 'department.updated',
                subject: $department,
                changes: $changes
            );
        }

        return redirect()
            ->route('master.departments.index')
            ->with('success', "Data departemen {$department->name} berhasil diperbarui.");
    }

    /**
     * Remove the specified department if not referenced.
     */
    public function destroy(Department $department): RedirectResponse
    {
        $this->authorize('delete', $department);

        if (! $department->canBeDeleted()) {
            return redirect()
                ->back()
                ->with('error', 'Departemen tidak dapat dihapus karena masih memiliki divisi atau karyawan terkait. Silakan nonaktifkan status departemen.');
        }

        $name = $department->name;
        $code = $department->code;

        $department->delete();

        $this->auditLogService->log(
            action: 'department.deleted',
            subject: Department::class,
            changes: [
                'name' => $name,
                'code' => $code,
            ]
        );

        return redirect()
            ->route('master.departments.index')
            ->with('success', "Departemen {$name} ({$code}) berhasil dihapus.");
    }

    /**
     * Toggle active/inactive status of a department.
     */
    public function toggleStatus(Department $department): RedirectResponse
    {
        $this->authorize('update', $department);

        $previous = $department->is_active;
        $department->is_active = ! $previous;
        $department->save();

        $statusText = $department->is_active ? 'diaktifkan' : 'dinonaktifkan';

        $this->auditLogService->log(
            action: 'department.status_toggled',
            subject: $department,
            changes: [
                'previous_status' => $previous,
                'new_status' => $department->is_active,
            ]
        );

        return redirect()
            ->back()
            ->with('success', "Departemen {$department->name} berhasil {$statusText}.");
    }

    /**
     * Get active divisions belonging to this department for cascading dropdowns.
     */
    public function divisions(Department $department): JsonResponse
    {
        $divisions = $department->divisions()
            ->active()
            ->orderBy('name')
            ->get(['id', 'department_id', 'name', 'code']);

        return response()->json($divisions);
    }
}
