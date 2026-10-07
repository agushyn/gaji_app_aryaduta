<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreDivisionRequest;
use App\Http\Requests\UpdateDivisionRequest;
use App\Models\Department;
use App\Models\Division;
use App\Services\AuditLogService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DivisionController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        protected AuditLogService $auditLogService
    ) {}

    /**
     * Display a listing of divisions with search and filters.
     */
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Division::class);

        $query = Division::query()->with(['office', 'department'])->withCount('employees');

        if ($request->filled('search')) {
            $query->search($request->string('search')->trim());
        }

        if ($request->filled('office_id') && $request->input('office_id') !== 'all') {
            $query->filterOffice((int) $request->input('office_id'));
        }

        if ($request->filled('department_id') && $request->input('department_id') !== 'all') {
            $query->filterDepartment((int) $request->input('department_id'));
        }

        if ($request->filled('status') && $request->input('status') !== 'all') {
            $query->where('is_active', $request->input('status') === 'active');
        }

        $divisions = $query->orderBy('name')->paginate(10)->withQueryString();

        $activeOffices = \App\Models\Office::active()->orderBy('name')->get(['id', 'name', 'code']);
        $activeDepartments = Department::active()->orderBy('name')->get(['id', 'name', 'code']);

        $counts = [
            'total' => Division::count(),
            'active' => Division::where('is_active', true)->count(),
            'inactive' => Division::where('is_active', false)->count(),
        ];

        return Inertia::render('Divisions/Index', [
            'divisions' => $divisions,
            'offices' => $activeOffices,
            'departments' => $activeDepartments,
            'counts' => $counts,
            'filters' => $request->only(['search', 'office_id', 'department_id', 'status']),
        ]);
    }

    /**
     * Store a newly created division.
     */
    public function store(StoreDivisionRequest $request): RedirectResponse
    {
        $this->authorize('create', Division::class);

        $division = Division::create($request->validated());

        $this->auditLogService->log(
            action: 'division.created',
            subject: $division,
            changes: [
                'office_id' => $division->office_id,
                'department_id' => $division->department_id,
                'name' => $division->name,
                'code' => $division->code,
                'description' => $division->description,
                'is_active' => $division->is_active,
            ]
        );

        return redirect()
            ->route('master.divisions.index')
            ->with('success', "Divisi {$division->name} berhasil ditambahkan.");
    }

    /**
     * Update the specified division.
     */
    public function update(UpdateDivisionRequest $request, Division $division): RedirectResponse
    {
        $this->authorize('update', $division);

        $oldData = $division->only(['office_id', 'department_id', 'name', 'code', 'description', 'is_active']);
        $division->update($request->validated());

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
                action: 'division.updated',
                subject: $division,
                changes: $changes
            );
        }

        return redirect()
            ->route('master.divisions.index')
            ->with('success', "Data divisi {$division->name} berhasil diperbarui.");
    }

    /**
     * Remove the specified division if not referenced by employees.
     */
    public function destroy(Division $division): RedirectResponse
    {
        $this->authorize('delete', $division);

        if (! $division->canBeDeleted()) {
            return redirect()
                ->back()
                ->with('error', 'Divisi tidak dapat dihapus karena masih digunakan oleh data karyawan. Silakan nonaktifkan status divisi.');
        }

        $name = $division->name;
        $code = $division->code;

        $division->delete();

        $this->auditLogService->log(
            action: 'division.deleted',
            subject: Division::class,
            changes: [
                'name' => $name,
                'code' => $code,
            ]
        );

        return redirect()
            ->route('master.divisions.index')
            ->with('success', "Divisi {$name} berhasil dihapus.");
    }

    /**
     * Toggle active/inactive status of a division.
     */
    public function toggleStatus(Division $division): RedirectResponse
    {
        $this->authorize('update', $division);

        $previous = $division->is_active;
        $division->is_active = ! $previous;
        $division->save();

        $statusText = $division->is_active ? 'diaktifkan' : 'dinonaktifkan';

        $this->auditLogService->log(
            action: 'division.status_toggled',
            subject: $division,
            changes: [
                'previous_status' => $previous,
                'new_status' => $division->is_active,
            ]
        );

        return redirect()
            ->back()
            ->with('success', "Divisi {$division->name} berhasil {$statusText}.");
    }
}
