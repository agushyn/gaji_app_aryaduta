<?php

namespace App\Http\Controllers;

use App\Enums\DeliveryStatus;
use App\Enums\PayrollPeriodStatus;
use App\Models\PayrollPeriod;
use App\Models\Payslip;
use App\Models\PayslipDelivery;
use App\Services\DeliveryService;
use DomainException;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DeliveryController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        protected DeliveryService $deliveryService
    ) {}

    /**
     * Display a listing of payslip deliveries.
     */
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', PayslipDelivery::class);

        $query = PayslipDelivery::query()
            ->with([
                'payslip:id,slip_number,payroll_period_id,employee_name_snapshot,employee_number_snapshot,net_salary',
                'payslip.payrollPeriod:id,period_code',
                'attempts',
            ]);

        if ($request->filled('channel') && $request->input('channel') !== 'all') {
            $query->forChannel($request->input('channel'));
        }

        if ($request->filled('status') && $request->input('status') !== 'all') {
            $query->filterStatus($request->input('status'));
        }

        if ($request->filled('period_id') && $request->input('period_id') !== 'all') {
            $query->whereHas('payslip', function ($q) use ($request) {
                $q->where('payroll_period_id', $request->input('period_id'));
            });
        }

        if ($request->filled('search')) {
            $search = trim($request->input('search'));
            $query->where(function ($q) use ($search) {
                $q->where('recipient', 'like', "%{$search}%")
                    ->orWhereHas('payslip', function ($sq) use ($search) {
                        $sq->where('employee_name_snapshot', 'like', "%{$search}%")
                            ->orWhere('employee_number_snapshot', 'like', "%{$search}%")
                            ->orWhere('slip_number', 'like', "%{$search}%");
                    });
            });
        }

        $deliveries = $query->latest('updated_at')->paginate(15)->withQueryString();

        $periods = PayrollPeriod::latest('start_date')
            ->select('id', 'period_code', 'status')
            ->get();

        $counts = [
            'total' => PayslipDelivery::count(),
            'queued' => PayslipDelivery::where('status', DeliveryStatus::QUEUED->value)->count(),
            'processing' => PayslipDelivery::where('status', DeliveryStatus::PROCESSING->value)->count(),
            'sent' => PayslipDelivery::where('status', DeliveryStatus::SENT->value)->count(),
            'failed' => PayslipDelivery::where('status', DeliveryStatus::FAILED->value)->count(),
        ];

        return Inertia::render('Deliveries/Index', [
            'deliveries' => $deliveries,
            'periods' => $periods,
            'counts' => $counts,
            'filters' => $request->only(['channel', 'status', 'period_id', 'search']),
            'can' => [
                'send' => $request->user()->can('send', PayslipDelivery::class),
                'retry' => $request->user()->hasPermission('deliveries.retry'),
            ],
        ]);
    }

    /**
     * Queue email delivery for a single finalized payslip.
     */
    public function sendEmail(Request $request, Payslip $payslip): RedirectResponse
    {
        $this->authorize('send', PayslipDelivery::class);

        try {
            $this->deliveryService->queueEmailDelivery($payslip, $request->user());

            return redirect()->back()->with('success', "Slip gaji {$payslip->slip_number} telah dimasukkan ke antrean pengiriman email.");
        } catch (DomainException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    /**
     * Queue email deliveries for all finalized payslips in a period.
     */
    public function sendPeriodEmails(Request $request, PayrollPeriod $payrollPeriod): RedirectResponse
    {
        $this->authorize('send', PayslipDelivery::class);

        try {
            $result = $this->deliveryService->queuePeriodEmailDeliveries($payrollPeriod, $request->user());

            return redirect()->back()->with(
                'success',
                "Pengiriman email diproses: {$result['queued']} slip berhasil diantrekan, {$result['skipped']} slip dilewati (tanpa email valid)."
            );
        } catch (DomainException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    /**
     * Queue WhatsApp delivery for a single finalized payslip.
     */
    public function sendWhatsApp(Request $request, Payslip $payslip): RedirectResponse
    {
        $this->authorize('send', PayslipDelivery::class);

        try {
            $this->deliveryService->queueWhatsAppDelivery($payslip, $request->user());

            return redirect()->back()->with('success', "Slip gaji {$payslip->slip_number} telah dimasukkan ke antrean pengiriman WhatsApp.");
        } catch (DomainException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    /**
     * Queue WhatsApp deliveries for all finalized payslips in a period.
     */
    public function sendPeriodWhatsApp(Request $request, PayrollPeriod $payrollPeriod): RedirectResponse
    {
        $this->authorize('send', PayslipDelivery::class);

        try {
            $result = $this->deliveryService->queuePeriodWhatsAppDeliveries($payrollPeriod, $request->user());

            return redirect()->back()->with(
                'success',
                "Pengiriman WhatsApp diproses: {$result['queued']} slip berhasil diantrekan, {$result['skipped']} slip dilewati (tanpa nomor WhatsApp valid)."
            );
        } catch (DomainException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    /**
     * Retry a failed delivery.
     */
    public function retry(Request $request, PayslipDelivery $delivery): RedirectResponse
    {
        $this->authorize('retry', $delivery);

        try {
            $this->deliveryService->retryDelivery($delivery, $request->user());

            return redirect()->back()->with('success', "Pengiriman ulang slip {$delivery->payslip->slip_number} berhasil dimasukkan ke antrean.");
        } catch (DomainException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    /**
     * Display page for selecting period, channel, and recipients for bulk delivery.
     */
    public function bulkCreate(Request $request): Response
    {
        $this->authorize('send', PayslipDelivery::class);

        $finalizedPeriods = PayrollPeriod::query()
            ->where('status', PayrollPeriodStatus::FINALIZED->value)
            ->latest('start_date')
            ->select('id', 'period_code', 'start_date', 'end_date', 'status')
            ->get();

        $selectedPeriodId = (int) $request->input('period_id', $finalizedPeriods->first()?->id ?? 0);
        $selectedChannel = $request->input('channel', 'both');

        $preview = null;
        if ($selectedPeriodId > 0) {
            try {
                $preview = $this->deliveryService->getBulkDeliveryPreview($selectedPeriodId, $selectedChannel);
            } catch (DomainException) {
                $preview = null;
            }
        }

        return Inertia::render('Deliveries/Bulk', [
            'periods' => $finalizedPeriods,
            'selectedPeriodId' => $selectedPeriodId,
            'selectedChannel' => $selectedChannel,
            'preview' => $preview,
            'can' => [
                'send' => $request->user()->can('send', PayslipDelivery::class),
                'viewFinancial' => $request->user()->hasPermission('payslips.financial-view'),
            ],
        ]);
    }

    /**
     * Fetch recipient preview data via JSON for dynamic client updates.
     */
    public function bulkPreview(Request $request): JsonResponse
    {
        $this->authorize('send', PayslipDelivery::class);

        $request->validate([
            'period_id' => ['required', 'integer', 'exists:payroll_periods,id'],
            'channel' => ['required', 'string', 'in:email,whatsapp,both'],
        ]);

        try {
            $preview = $this->deliveryService->getBulkDeliveryPreview(
                (int) $request->input('period_id'),
                (string) $request->input('channel')
            );

            return response()->json($preview);
        } catch (DomainException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }

    /**
     * Dispatch bulk delivery for chosen period, channel, and specific payslip selections.
     */
    public function bulkStore(Request $request): RedirectResponse
    {
        $this->authorize('send', PayslipDelivery::class);

        $validated = $request->validate([
            'period_id' => ['required', 'integer', 'exists:payroll_periods,id'],
            'channel' => ['required', 'string', 'in:email,whatsapp,both'],
            'payslip_ids' => ['required', 'array', 'min:1'],
            'payslip_ids.*' => ['integer', 'exists:payslips,id'],
        ], [
            'payslip_ids.required' => 'Pilih minimal satu karyawan/slip gaji untuk dikirim.',
            'payslip_ids.min' => 'Pilih minimal satu karyawan/slip gaji untuk dikirim.',
        ]);

        try {
            $result = $this->deliveryService->queueBulkDelivery(
                periodId: (int) $validated['period_id'],
                channel: $validated['channel'],
                payslipIds: $validated['payslip_ids'],
                actor: $request->user()
            );

            $msgParts = [];
            if ($result['email_queued'] > 0) {
                $msgParts[] = "{$result['email_queued']} email diantrekan";
            }
            if ($result['whatsapp_queued'] > 0) {
                $msgParts[] = "{$result['whatsapp_queued']} WhatsApp diantrekan";
            }
            if ($result['skipped'] > 0) {
                $msgParts[] = "{$result['skipped']} tujuan dilewati (kontak tidak valid/sudah terkirim)";
            }

            $successMsg = 'Pengiriman massal berhasil diproses: '.implode(', ', $msgParts).'.';

            return redirect()->route('deliveries.index', ['period_id' => $validated['period_id']])
                ->with('success', $successMsg);
        } catch (DomainException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    /**
     * Selectively retry all failed deliveries matching period and/or channel filter.
     */
    public function retryFailed(Request $request): RedirectResponse
    {
        $this->authorize('send', PayslipDelivery::class);

        $periodId = $request->filled('period_id') && $request->input('period_id') !== 'all'
            ? (int) $request->input('period_id')
            : null;

        $channel = $request->filled('channel') && $request->input('channel') !== 'all'
            ? (string) $request->input('channel')
            : null;

        $count = $this->deliveryService->retryFailedDeliveries($periodId, $channel, $request->user());

        if ($count === 0) {
            return redirect()->back()->with('info', 'Tidak ada pengiriman gagal yang memenuhi kriteria untuk dikirim ulang.');
        }

        return redirect()->back()->with('success', "Sebanyak {$count} pengiriman yang gagal berhasil dimasukkan kembali ke antrean.");
    }
}
