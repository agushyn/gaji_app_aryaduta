<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreOfficeRequest;
use App\Http\Requests\UpdateOfficeRequest;
use App\Models\Office;
use App\Services\AuditLogService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class OfficeController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        protected AuditLogService $auditLogService
    ) {}

    /**
     * Display a listing of offices.
     */
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Office::class);

        $query = Office::query()
            ->withCount(['divisions', 'employees']);

        if ($request->filled('search')) {
            $query->search($request->string('search')->trim());
        }

        if ($request->filled('status') && $request->input('status') !== 'all') {
            $query->where('is_active', $request->input('status') === 'active');
        }

        $offices = $query->orderBy('name')->paginate(10)->withQueryString();

        $counts = [
            'total' => Office::count(),
            'active' => Office::where('is_active', true)->count(),
            'inactive' => Office::where('is_active', false)->count(),
        ];

        return Inertia::render('Offices/Index', [
            'offices' => $offices,
            'counts' => $counts,
            'filters' => $request->only(['search', 'status']),
        ]);
    }

    /**
     * Store a newly created office.
     */
    public function store(StoreOfficeRequest $request): RedirectResponse
    {
        $this->authorize('create', Office::class);

        $office = Office::create($request->validated());

        $this->auditLogService->log(
            action: 'office.created',
            subject: $office,
            changes: [
                'name' => $office->name,
                'code' => $office->code,
                'address' => $office->address,
                'phone' => $office->phone,
                'email' => $office->email,
                'is_active' => $office->is_active,
            ]
        );

        return redirect()
            ->route('master.offices.index')
            ->with('success', "Kantor {$office->name} berhasil ditambahkan.");
    }

    /**
     * Update the specified office.
     */
    public function update(UpdateOfficeRequest $request, Office $office): RedirectResponse
    {
        $this->authorize('update', $office);

        $oldData = $office->only(['name', 'code', 'address', 'phone', 'email', 'is_active']);
        $office->update($request->validated());

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
                action: 'office.updated',
                subject: $office,
                changes: $changes
            );
        }

        return redirect()
            ->route('master.offices.index')
            ->with('success', "Data kantor {$office->name} berhasil diperbarui.");
    }

    /**
     * Remove the specified office if not referenced by divisions, employees, or payslips.
     */
    public function destroy(Office $office): RedirectResponse
    {
        $this->authorize('delete', $office);

        if (! $office->canBeDeleted()) {
            return redirect()
                ->route('master.offices.index')
                ->with('error', 'Kantor tidak dapat dihapus karena masih digunakan oleh divisi, karyawan, atau slip gaji. Silakan nonaktifkan kantor.');
        }

        $name = $office->name;
        $code = $office->code;

        $office->delete();

        $this->auditLogService->log(
            action: 'office.deleted',
            subject: Office::class,
            changes: [
                'name' => $name,
                'code' => $code,
            ]
        );

        return redirect()
            ->route('master.offices.index')
            ->with('success', "Kantor {$name} berhasil dihapus.");
    }

    /**
     * Toggle active/inactive status of an office.
     */
    public function toggleStatus(Office $office): RedirectResponse
    {
        $this->authorize('update', $office);

        $previous = $office->is_active;
        $office->is_active = ! $previous;
        $office->save();

        $statusText = $office->is_active ? 'diaktifkan' : 'dinonaktifkan';

        $this->auditLogService->log(
            action: 'office.status_toggled',
            subject: $office,
            changes: [
                'previous_status' => $previous,
                'new_status' => $office->is_active,
            ]
        );

        return redirect()
            ->back()
            ->with('success', "Kantor {$office->name} berhasil {$statusText}.");
    }

    /**
     * Get active divisions for this office as JSON (for cascading dropdowns).
     */
    public function divisions(Office $office): JsonResponse
    {
        $this->authorize('view', $office);

        $divisions = $office->divisions()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'office_id', 'name', 'code']);

        return response()->json($divisions);
    }
}
