<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePositionRequest;
use App\Http\Requests\UpdatePositionRequest;
use App\Models\Position;
use App\Services\AuditLogService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PositionController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        protected AuditLogService $auditLogService
    ) {}

    /**
     * Display a listing of positions.
     */
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Position::class);

        $query = Position::query()
            ->withCount('employees');

        if ($request->filled('search')) {
            $query->search($request->string('search')->trim());
        }

        if ($request->filled('status') && $request->input('status') !== 'all') {
            $query->where('is_active', $request->input('status') === 'active');
        }

        $positions = $query->orderBy('name')->paginate(10)->withQueryString();

        $counts = [
            'total' => Position::count(),
            'active' => Position::where('is_active', true)->count(),
            'inactive' => Position::where('is_active', false)->count(),
        ];

        return Inertia::render('Positions/Index', [
            'positions' => $positions,
            'counts' => $counts,
            'filters' => $request->only(['search', 'status']),
        ]);
    }

    /**
     * Store a newly created position.
     */
    public function store(StorePositionRequest $request): RedirectResponse
    {
        $this->authorize('create', Position::class);

        $position = Position::create($request->validated());

        $this->auditLogService->log(
            action: 'position.created',
            subject: $position,
            changes: [
                'name' => $position->name,
                'code' => $position->code,
                'description' => $position->description,
                'is_active' => $position->is_active,
            ]
        );

        return redirect()
            ->route('master.positions.index')
            ->with('success', "Jabatan {$position->name} berhasil ditambahkan.");
    }

    /**
     * Update the specified position.
     */
    public function update(UpdatePositionRequest $request, Position $position): RedirectResponse
    {
        $this->authorize('update', $position);

        $oldData = $position->only(['name', 'code', 'description', 'is_active']);
        $position->update($request->validated());

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
                action: 'position.updated',
                subject: $position,
                changes: $changes
            );
        }

        return redirect()
            ->route('master.positions.index')
            ->with('success', "Data jabatan {$position->name} berhasil diperbarui.");
    }

    /**
     * Remove the specified position if not referenced by employees.
     */
    public function destroy(Position $position): RedirectResponse
    {
        $this->authorize('delete', $position);

        if (! $position->canBeDeleted()) {
            return redirect()
                ->route('master.positions.index')
                ->with('error', 'Jabatan tidak dapat dihapus karena masih digunakan oleh karyawan. Silakan nonaktifkan jabatan.');
        }

        $name = $position->name;
        $code = $position->code;

        $position->delete();

        $this->auditLogService->log(
            action: 'position.deleted',
            subject: Position::class,
            changes: [
                'name' => $name,
                'code' => $code,
            ]
        );

        return redirect()
            ->route('master.positions.index')
            ->with('success', "Jabatan {$name} berhasil dihapus.");
    }

    /**
     * Toggle active/inactive status of a position.
     */
    public function toggleStatus(Position $position): RedirectResponse
    {
        $this->authorize('update', $position);

        $previous = $position->is_active;
        $position->is_active = ! $previous;
        $position->save();

        $statusText = $position->is_active ? 'diaktifkan' : 'dinonaktifkan';

        $this->auditLogService->log(
            action: 'position.status_toggled',
            subject: $position,
            changes: [
                'previous_status' => $previous,
                'new_status' => $position->is_active,
            ]
        );

        return redirect()
            ->back()
            ->with('success', "Jabatan {$position->name} berhasil {$statusText}.");
    }

    /**
     * Get active positions as JSON.
     */
    public function listActive(): JsonResponse
    {
        $this->authorize('viewAny', Position::class);

        $positions = Position::where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'code']);

        return response()->json($positions);
    }
}
