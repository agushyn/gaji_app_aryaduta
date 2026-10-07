<?php

namespace App\Http\Controllers;

use App\Services\SmtpConfigService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class EmailSettingController extends Controller
{
    public function __construct(
        protected SmtpConfigService $smtpService
    ) {}

    /**
     * Show form for editing email SMTP integration settings.
     */
    public function edit(Request $request): Response
    {
        abort_if(! $request->user()->hasPermission('settings.view'), 403, 'Akses ditolak.');

        $config = $this->smtpService->getSafeConfig();

        return Inertia::render('Settings/Email', [
            'config' => $config,
            'canManage' => $request->user()->hasPermission('settings.manage'),
        ]);
    }

    /**
     * Update SMTP configuration.
     */
    public function update(Request $request): RedirectResponse
    {
        abort_if(! $request->user()->hasPermission('settings.manage'), 403, 'Akses ditolak.');

        $validated = $request->validate([
            'endpoint' => ['required', 'string', 'max:255'],
            'port' => ['required', 'integer', 'min:1', 'max:65535'],
            'encryption' => ['required', 'string', 'in:tls,ssl,none'],
            'username' => ['nullable', 'string', 'max:255'],
            'password' => ['nullable', 'string', 'max:255'],
            'sender_identifier' => ['required', 'email', 'max:255'],
            'from_name' => ['required', 'string', 'max:255'],
            'is_active' => ['boolean'],
        ], [
            'endpoint.required' => 'Host SMTP wajib diisi.',
            'port.required' => 'Port SMTP wajib diisi.',
            'sender_identifier.required' => 'Alamat pengirim (From Address) wajib diisi.',
            'sender_identifier.email' => 'Format alamat pengirim harus berupa email yang valid.',
            'from_name.required' => 'Nama pengirim wajib diisi.',
        ]);

        $this->smtpService->saveConfig($validated, $request->user());

        return redirect()->back()->with('success', 'Pengaturan server email SMTP berhasil disimpan.');
    }

    /**
     * Test SMTP connection without sending any payroll data.
     */
    public function testConnection(Request $request): RedirectResponse
    {
        abort_if(! $request->user()->hasPermission('settings.manage'), 403, 'Akses ditolak.');

        $request->validate([
            'recipient' => ['required', 'email'],
        ], [
            'recipient.required' => 'Alamat email tujuan uji coba wajib diisi.',
            'recipient.email' => 'Format email tujuan uji coba tidak valid.',
        ]);

        $result = $this->smtpService->testConnection($request->input('recipient'));

        if ($result['success']) {
            return redirect()->back()->with('success', $result['message']);
        }

        return redirect()->back()->with('error', $result['message']);
    }
}
