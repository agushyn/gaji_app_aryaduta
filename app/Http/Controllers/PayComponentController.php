<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePayComponentRequest;
use App\Http\Requests\UpdatePayComponentRequest;
use App\Models\PayComponent;
use App\Services\PayComponentService;
use DomainException;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PayComponentController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        protected PayComponentService $componentService
    ) {}

    /**
     * Display listing of pay components.
     */
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', PayComponent::class);

        $query = PayComponent::query()->withCount('payslipItems');

        if ($request->filled('search')) {
            $query->search($request->string('search')->trim());
        }

        if ($request->filled('type')) {
            $query->where('type', $request->string('type'));
        }

        if ($request->filled('status')) {
            $isActive = $request->string('status') === 'active';
            $query->where('is_active', $isActive);
        }

        $components = $query->ordered()->paginate(15)->withQueryString();

        $counts = [
            'total' => PayComponent::count(),
            'earnings' => PayComponent::where('type', 'earning')->count(),
            'deductions' => PayComponent::where('type', 'deduction')->count(),
            'active' => PayComponent::where('is_active', true)->count(),
        ];

        return Inertia::render('PayComponents/Index', [
            'components' => $components,
            'counts' => $counts,
            'filters' => $request->only(['search', 'type', 'status']),
        ]);
    }

    /**
     * Store a newly created pay component.
     */
    public function store(StorePayComponentRequest $request): RedirectResponse
    {
        $this->authorize('create', PayComponent::class);

        $component = $this->componentService->createComponent($request->validated());

        return redirect()
            ->route('pay-components.index')
            ->with('success', "Komponen gaji {$component->name} ({$component->code}) berhasil ditambahkan.");
    }

    /**
     * Update an existing pay component.
     */
    public function update(UpdatePayComponentRequest $request, PayComponent $payComponent): RedirectResponse
    {
        $this->authorize('update', $payComponent);

        $component = $this->componentService->updateComponent($payComponent, $request->validated());

        return redirect()
            ->route('pay-components.index')
            ->with('success', "Komponen gaji {$component->name} berhasil diperbarui.");
    }

    /**
     * Toggle active status.
     */
    public function toggleStatus(PayComponent $payComponent): RedirectResponse
    {
        $this->authorize('update', $payComponent);

        $updated = $this->componentService->toggleActive($payComponent);
        $statusText = $updated->is_active ? 'diaktifkan' : 'dinonaktifkan';

        return redirect()
            ->back()
            ->with('success', "Komponen gaji {$updated->name} berhasil {$statusText}.");
    }

    /**
     * Remove pay component.
     */
    public function destroy(PayComponent $payComponent): RedirectResponse
    {
        $this->authorize('delete', $payComponent);

        try {
            $name = $payComponent->name;
            $this->componentService->deleteComponent($payComponent);

            return redirect()
                ->route('pay-components.index')
                ->with('success', "Komponen gaji {$name} berhasil dihapus.");
        } catch (DomainException $e) {
            return redirect()
                ->back()
                ->with('error', $e->getMessage());
        }
    }
}
