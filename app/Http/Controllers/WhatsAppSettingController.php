<?php

namespace App\Http\Controllers;

use App\Services\WhatsAppConfigService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class WhatsAppSettingController extends Controller
{
    public function __construct(
        protected WhatsAppConfigService $whatsAppService
    ) {}

    /**
     * Show form for editing WhatsApp integration settings.
     */
    public function edit(Request $request): Response
    {
        abort_if(! $request->user()->hasPermission('settings.view'), 403, 'Akses ditolak.');

        $config = $this->whatsAppService->getSafeConfig();

        return Inertia::render('Settings/WhatsApp', [
            'config' => $config,
            'canManage' => $request->user()->hasPermission('settings.manage'),
        ]);
    }

    /**
     * Update WhatsApp integration configuration.
     */
    public function update(Request $request): RedirectResponse
    {
        abort_if(! $request->user()->hasPermission('settings.manage'), 403, 'Akses ditolak.');

        $validated = $request->validate([
            'driver' => ['required', 'string', 'in:fonnte,generic,mock'],
            'endpoint' => ['nullable', 'string', 'max:500'],
            'sender_identifier' => ['nullable', 'string', 'max:255'],
            'secret' => ['nullable', 'string', 'max:500'],
            'webhook_secret' => ['nullable', 'string', 'max:255'],
            'rate_limit_per_minute' => ['required', 'integer', 'min:1', 'max:120'],
            'timeout' => ['required', 'integer', 'min:5', 'max:60'],
            'is_active' => ['boolean'],
        ], [
            'driver.required' => 'Pilihan driver gateway WhatsApp wajib ditentukan.',
            'driver.in' => 'Driver gateway yang dipilih tidak valid.',
            'rate_limit_per_minute.required' => 'Batas pengiriman per menit wajib diisi.',
            'timeout.required' => 'Waktu batas koneksi (timeout) wajib diisi.',
        ]);

        $this->whatsAppService->saveConfig($validated, $request->user());

        return redirect()->back()->with('success', 'Pengaturan gateway WhatsApp berhasil disimpan.');
    }

    /**
     * Test connection to WhatsApp gateway without sending any payroll data.
     */
    public function testConnection(Request $request): RedirectResponse|JsonResponse
    {
        abort_if(! $request->user()->hasPermission('settings.manage'), 403, 'Akses ditolak.');

        $request->validate([
            'recipient' => ['required', 'string', 'max:30'],
        ], [
            'recipient.required' => 'Nomor WhatsApp tujuan uji coba wajib diisi.',
        ]);

        $result = $this->whatsAppService->testConnection($request->input('recipient'));

        if ($request->wantsJson()) {
            return response()->json($result, $result['success'] ? 200 : 422);
        }

        if ($result['success']) {
            return redirect()->back()->with('success', $result['message']);
        }

        return redirect()->back()->with('error', $result['message']);
    }
}
