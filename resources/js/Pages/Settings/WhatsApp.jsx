import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, router, useForm, usePage } from '@inertiajs/react';
import {
    Activity,
    AlertCircle,
    CheckCircle,
    Clock,
    Copy,
    Eye,
    EyeOff,
    FileText,
    Info,
    MessageSquare,
    Radio,
    RefreshCw,
    Save,
    Send,
    ShieldCheck,
    Smartphone,
    Sliders,
    Wifi,
    WifiOff,
} from 'lucide-react';
import { useState } from 'react';

export default function WhatsAppSettings({ config, canManage }) {
    const { auth, flash } = usePage().props;
    const [showSecret, setShowSecret] = useState(false);
    const [copiedWebhook, setCopiedWebhook] = useState(false);
    const [checkingSession, setCheckingSession] = useState(false);
    const [sessionStatusResult, setSessionStatusResult] = useState(null);

    // Form for WhatsApp Gateway Configuration
    const { data, setData, put, processing, errors } = useForm({
        driver: config.driver || 'openwa',
        endpoint: config.endpoint || (config.driver === 'openwa' ? 'http://localhost:3000/api' : 'https://api.fonnte.com/send'),
        sender_identifier: config.sender_identifier || '',
        session_id: config.session_id || 'default',
        secret: '',
        webhook_secret: '',
        rate_limit_per_minute: config.rate_limit_per_minute || 30,
        timeout: config.timeout || 30,
        connection_timeout: config.connection_timeout || 10,
        request_timeout: config.request_timeout || 30,
        send_interval_seconds: config.send_interval_seconds || 2,
        max_retries: config.max_retries || 3,
        document_mode: config.document_mode || 'base64',
        is_active: Boolean(config.is_active),
    });

    // Form for Test Connection Ping
    const {
        data: testData,
        setData: setTestData,
        post: postTest,
        processing: testProcessing,
        errors: testErrors,
    } = useForm({
        recipient: '',
    });

    const handleSave = (e) => {
        e.preventDefault();
        put(route('settings.whatsapp.update'), {
            preserveScroll: true,
        });
    };

    const handleTest = (e) => {
        e.preventDefault();
        postTest(route('settings.whatsapp.test'), {
            preserveScroll: true,
        });
    };

    const handleCheckSession = async () => {
        setCheckingSession(true);
        try {
            const response = await fetch(route('settings.whatsapp.session-status'), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                },
            });
            const result = await response.json();
            setSessionStatusResult(result);
        } catch (err) {
            setSessionStatusResult({
                success: false,
                api_connected: false,
                session_status: 'NETWORK_ERROR',
                is_ready: false,
                message: 'Gagal menghubungi server untuk memeriksa status session.',
            });
        } finally {
            setCheckingSession(false);
        }
    };

    const copyWebhookUrl = () => {
        if (config.webhook_url) {
            navigator.clipboard.writeText(config.webhook_url);
            setCopiedWebhook(true);
            setTimeout(() => setCopiedWebhook(false), 2000);
        }
    };

    const isOpenWa = data.driver === 'openwa';

    // Current Session Display Status (from live check or persisted config)
    const currentSessionStatus = sessionStatusResult?.session_status || config.last_session_status || 'UNKNOWN';
    const isSessionReady = sessionStatusResult ? sessionStatusResult.is_ready : (config.last_session_status === 'CONNECTED');

    return (
        <AuthenticatedLayout header="Pengaturan WhatsApp Gateway">
            <Head title="Pengaturan WhatsApp" />

            <div className="max-w-5xl mx-auto space-y-6">
                {/* Header Section */}
                <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                    <div>
                        <h1 className="text-xl font-bold text-slate-900 tracking-tight flex items-center gap-2">
                            <Smartphone className="h-6 w-6 text-emerald-600" />
                            Konfigurasi Gateway WhatsApp & OpenWA
                        </h1>
                        <p className="mt-1 text-xs text-slate-500">
                            Kelola provider WhatsApp (OpenWA, Fonnte, Mock) untuk pengiriman dokumen slip gaji PDF secara terenkripsi dan terlacak.
                        </p>
                    </div>

                    <div className="flex items-center gap-2">
                        <span
                            className={`inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold border ${
                                config.is_active
                                    ? 'bg-emerald-50 text-emerald-700 border-emerald-200'
                                    : 'bg-amber-50 text-amber-700 border-amber-200'
                            }`}
                        >
                            <span
                                className={`h-2 w-2 rounded-full ${
                                    config.is_active ? 'bg-emerald-500 animate-pulse' : 'bg-amber-500'
                                }`}
                            />
                            {config.is_active ? 'Gateway Aktif' : 'Gateway Nonaktif'}
                        </span>
                    </div>
                </div>

                {/* Status Banners */}
                {flash?.success && (
                    <div className="rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-emerald-800 text-sm flex items-start gap-3">
                        <CheckCircle className="h-5 w-5 text-emerald-600 shrink-0 mt-0.5" />
                        <div>
                            <p className="font-semibold">Berhasil</p>
                            <p className="text-xs text-emerald-700 mt-0.5">{flash.success}</p>
                        </div>
                    </div>
                )}

                {flash?.error && (
                    <div className="rounded-xl border border-rose-200 bg-rose-50 p-4 text-rose-800 text-sm flex items-start gap-3">
                        <AlertCircle className="h-5 w-5 text-rose-600 shrink-0 mt-0.5" />
                        <div>
                            <p className="font-semibold">Perhatian</p>
                            <p className="text-xs text-rose-700 mt-0.5">{flash.error}</p>
                        </div>
                    </div>
                )}

                <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    {/* Main Settings Form (2 Cols) */}
                    <div className="lg:col-span-2 space-y-6">
                        <div className="rounded-2xl border border-slate-200 bg-white p-6 shadow-xs">
                            <form onSubmit={handleSave} className="space-y-5">
                                <div className="border-b border-slate-100 pb-4">
                                    <h2 className="text-sm font-semibold text-slate-800 uppercase tracking-wider">
                                        Adapter & Kredensial Provider
                                    </h2>
                                    <p className="text-xs text-slate-500 mt-1">
                                        Mendukung OpenWA Official REST API, Fonnte, Generic Webhook, dan Simulator Mock.
                                    </p>
                                </div>

                                {/* Active Toggle */}
                                <div className="flex items-center justify-between p-3.5 rounded-xl bg-slate-50 border border-slate-200">
                                    <div>
                                        <label htmlFor="is_active" className="text-sm font-semibold text-slate-800 block cursor-pointer">
                                            Aktifkan Pengiriman WhatsApp
                                        </label>
                                        <p className="text-xs text-slate-500">
                                            Bila aktif, pengiriman slip gaji ke nomor WhatsApp karyawan akan diproses melalui gateway ini.
                                        </p>
                                    </div>
                                    <label className="relative inline-flex items-center cursor-pointer">
                                        <input
                                            type="checkbox"
                                            id="is_active"
                                            checked={data.is_active}
                                            disabled={!canManage}
                                            onChange={(e) => setData('is_active', e.target.checked)}
                                            className="sr-only peer"
                                        />
                                        <div className="w-11 h-6 bg-slate-200 peer-focus:outline-none peer-focus:ring-2 peer-focus:ring-emerald-500 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-emerald-600"></div>
                                    </label>
                                </div>

                                {/* Driver and Device/Session Identifier */}
                                <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                    <div>
                                        <label className="block text-xs font-semibold text-slate-700 mb-1">
                                            Pilihan Driver Gateway <span className="text-rose-500">*</span>
                                        </label>
                                        <select
                                            value={data.driver}
                                            disabled={!canManage}
                                            onChange={(e) => {
                                                const drv = e.target.value;
                                                let defaultEndpoint = data.endpoint;
                                                if (drv === 'openwa' && (!defaultEndpoint || defaultEndpoint.includes('fonnte'))) {
                                                    defaultEndpoint = 'http://localhost:3000/api';
                                                } else if (drv === 'fonnte' && (!defaultEndpoint || defaultEndpoint.includes('localhost'))) {
                                                    defaultEndpoint = 'https://api.fonnte.com/send';
                                                }
                                                setData((prev) => ({
                                                    ...prev,
                                                    driver: drv,
                                                    endpoint: defaultEndpoint,
                                                }));
                                            }}
                                            className="w-full rounded-xl border border-slate-200 px-3.5 py-2 text-sm text-slate-800 focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500 disabled:bg-slate-50 disabled:text-slate-500 font-medium"
                                        >
                                            <option value="openwa">OpenWA (Official WA-Automate REST API Gateway)</option>
                                            <option value="fonnte">Fonnte (Indonesia Gateway Resmi)</option>
                                            <option value="generic">Generic REST / Webhook Gateway</option>
                                            <option value="mock">Mock Simulator (Pengujian Tanpa Biaya)</option>
                                        </select>
                                        {errors.driver && (
                                            <p className="mt-1 text-xs text-rose-600">{errors.driver}</p>
                                        )}
                                    </div>

                                    <div>
                                        <label className="block text-xs font-semibold text-slate-700 mb-1">
                                            {isOpenWa ? 'OpenWA Session ID' : 'Sender ID / Device Token'} <span className="text-rose-500">*</span>
                                        </label>
                                        <input
                                            type="text"
                                            value={isOpenWa ? data.session_id : data.sender_identifier}
                                            disabled={!canManage}
                                            onChange={(e) => {
                                                const val = e.target.value;
                                                if (isOpenWa) {
                                                    setData((prev) => ({
                                                        ...prev,
                                                        session_id: val,
                                                        sender_identifier: val,
                                                    }));
                                                } else {
                                                    setData('sender_identifier', val);
                                                }
                                            }}
                                            placeholder={isOpenWa ? 'contoh: default atau payroll_session' : 'contoh: device-01 atau nomor pengirim'}
                                            className="w-full rounded-xl border border-slate-200 px-3.5 py-2 text-sm text-slate-800 placeholder-slate-400 focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500 disabled:bg-slate-50 disabled:text-slate-500 font-mono"
                                        />
                                        <p className="mt-1 text-[11px] text-slate-400">
                                            {isOpenWa
                                                ? 'ID sesi WhatsApp Web yang dijalankan pada daemon OpenWA.'
                                                : 'Nama atau kode device pada penyedia gateway.'}
                                        </p>
                                        {(errors.session_id || errors.sender_identifier) && (
                                            <p className="mt-1 text-xs text-rose-600">{errors.session_id || errors.sender_identifier}</p>
                                        )}
                                    </div>
                                </div>

                                {/* Endpoint URL */}
                                <div>
                                    <label className="block text-xs font-semibold text-slate-700 mb-1">
                                        URL Base Endpoint API Gateway <span className="text-rose-500">*</span>
                                    </label>
                                    <input
                                        type="url"
                                        value={data.endpoint}
                                        disabled={!canManage}
                                        onChange={(e) => setData('endpoint', e.target.value)}
                                        placeholder={isOpenWa ? 'http://localhost:3000/api atau https://wa-gateway.example.com/api' : 'https://api.fonnte.com/send'}
                                        className="w-full rounded-xl border border-slate-200 px-3.5 py-2 text-sm text-slate-800 placeholder-slate-400 focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500 disabled:bg-slate-50 disabled:text-slate-500 font-mono"
                                    />
                                    <p className="mt-1 text-[11px] text-slate-400">
                                        {isOpenWa
                                            ? 'Base URL OpenWA REST API server (dikirim dengan header X-API-Key).'
                                            : 'URL endpoint pengiriman pesan provider.'}
                                    </p>
                                    {errors.endpoint && (
                                        <p className="mt-1 text-xs text-rose-600">{errors.endpoint}</p>
                                    )}
                                </div>

                                {/* Secret Token / API Key with Masking */}
                                <div>
                                    <div className="flex items-center justify-between mb-1">
                                        <label className="block text-xs font-semibold text-slate-700">
                                            {isOpenWa ? 'OpenWA API Key (X-API-Key)' : 'Token Rahasia / API Secret'}
                                        </label>
                                        {config.has_secret && (
                                            <span className="inline-flex items-center gap-1 text-[11px] font-medium text-emerald-600 bg-emerald-50 px-2 py-0.5 rounded-md border border-emerald-200">
                                                <ShieldCheck className="h-3.5 w-3.5" />
                                                Tersimpan terenkripsi
                                            </span>
                                        )}
                                    </div>
                                    <div className="relative">
                                        <input
                                            type={showSecret ? 'text' : 'password'}
                                            value={data.secret}
                                            disabled={!canManage}
                                            onChange={(e) => setData('secret', e.target.value)}
                                            placeholder={
                                                config.has_secret
                                                    ? '•••••••• (Biarkan kosong jika tidak ingin mengubah)'
                                                    : (isOpenWa ? 'Masukkan API Key OpenWA' : 'Masukkan API Token dari penyedia WhatsApp')
                                            }
                                            className="w-full rounded-xl border border-slate-200 pl-3.5 pr-10 py-2 text-sm text-slate-800 placeholder-slate-400 focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500 disabled:bg-slate-50 disabled:text-slate-500 font-mono"
                                        />
                                        <button
                                            type="button"
                                            onClick={() => setShowSecret(!showSecret)}
                                            className="absolute right-3 top-2.5 text-slate-400 hover:text-slate-600"
                                            aria-label="Tampilkan atau sembunyikan secret"
                                        >
                                            {showSecret ? (
                                                <EyeOff className="h-4 w-4" />
                                            ) : (
                                                <Eye className="h-4 w-4" />
                                            )}
                                        </button>
                                    </div>
                                    <p className="mt-1 text-[11px] text-slate-400">
                                        Kredensial dienkripsi AES-256 pada server dan tidak pernah diekspos utuh ke browser atau response API.
                                    </p>
                                    {errors.secret && (
                                        <p className="mt-1 text-xs text-rose-600">{errors.secret}</p>
                                    )}
                                </div>

                                {/* OpenWA Advanced Options (Document Delivery Mode & Intervals) */}
                                {isOpenWa && (
                                    <div className="border-t border-slate-100 pt-4 space-y-4">
                                        <div className="flex items-center gap-2">
                                            <Sliders className="h-4 w-4 text-emerald-600" />
                                            <h3 className="text-xs font-semibold text-slate-800 uppercase tracking-wider">
                                                Pengaturan Spesifik OpenWA & Optimasi Throughput
                                            </h3>
                                        </div>

                                        <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                            {/* Document Delivery Mode */}
                                            <div>
                                                <label className="block text-xs font-semibold text-slate-700 mb-1">
                                                    Mode Pengiriman Dokumen PDF
                                                </label>
                                                <select
                                                    value={data.document_mode}
                                                    disabled={!canManage}
                                                    onChange={(e) => setData('document_mode', e.target.value)}
                                                    className="w-full rounded-xl border border-slate-200 px-3.5 py-2 text-sm text-slate-800 focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500 disabled:bg-slate-50"
                                                >
                                                    <option value="base64">Base64 Data URI (Mandiri & Direkomendasikan)</option>
                                                    <option value="url">URL Bertanda Tangan Sementara (Expiring HMAC)</option>
                                                </select>
                                                <p className="mt-1 text-[11px] text-slate-400 leading-normal">
                                                    Base64 tidak membutuhkan OpenWA dapat mengakses balik server Laravel melalui jaringan publik.
                                                </p>
                                            </div>

                                            {/* Send Interval */}
                                            <div>
                                                <label className="block text-xs font-semibold text-slate-700 mb-1">
                                                    Jeda Antar-Pesan Massal (Detik)
                                                </label>
                                                <input
                                                    type="number"
                                                    min="1"
                                                    max="60"
                                                    value={data.send_interval_seconds}
                                                    disabled={!canManage}
                                                    onChange={(e) => setData('send_interval_seconds', Math.max(1, parseInt(e.target.value) || 2))}
                                                    className="w-full rounded-xl border border-slate-200 px-3.5 py-2 text-sm text-slate-800 focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500 disabled:bg-slate-50"
                                                />
                                                <p className="mt-1 text-[11px] text-slate-400 leading-normal">
                                                    Memberi jarak waktu antar slip pada antrean massal untuk mencegah pemblokiran oleh WhatsApp.
                                                </p>
                                            </div>

                                            {/* Connection & Request Timeouts */}
                                            <div>
                                                <label className="block text-xs font-semibold text-slate-700 mb-1">
                                                    Connection Timeout (Detik)
                                                </label>
                                                <input
                                                    type="number"
                                                    min="1"
                                                    max="60"
                                                    value={data.connection_timeout}
                                                    disabled={!canManage}
                                                    onChange={(e) => setData('connection_timeout', parseInt(e.target.value) || 10)}
                                                    className="w-full rounded-xl border border-slate-200 px-3.5 py-2 text-sm text-slate-800 focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500 disabled:bg-slate-50"
                                                />
                                            </div>

                                            <div>
                                                <label className="block text-xs font-semibold text-slate-700 mb-1">
                                                    Request Timeout Dokumen (Detik)
                                                </label>
                                                <input
                                                    type="number"
                                                    min="5"
                                                    max="180"
                                                    value={data.request_timeout}
                                                    disabled={!canManage}
                                                    onChange={(e) => setData('request_timeout', parseInt(e.target.value) || 30)}
                                                    className="w-full rounded-xl border border-slate-200 px-3.5 py-2 text-sm text-slate-800 focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500 disabled:bg-slate-50"
                                                />
                                            </div>
                                        </div>
                                    </div>
                                )}

                                {/* Rate Limiting & General Timeout */}
                                <div className="grid grid-cols-1 sm:grid-cols-2 gap-4 border-t border-slate-100 pt-4">
                                    <div>
                                        <label className="block text-xs font-semibold text-slate-700 mb-1">
                                            Batas Maksimal Pengiriman (Pesan / Menit)
                                        </label>
                                        <input
                                            type="number"
                                            min="1"
                                            max="120"
                                            value={data.rate_limit_per_minute}
                                            disabled={!canManage}
                                            onChange={(e) =>
                                                setData('rate_limit_per_minute', parseInt(e.target.value) || 30)
                                            }
                                            className="w-full rounded-xl border border-slate-200 px-3.5 py-2 text-sm text-slate-800 focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500 disabled:bg-slate-50"
                                        />
                                        <p className="mt-1 text-[11px] text-slate-400">
                                            Mencegah lonjakan trafik pengiriman yang berlebihan ke gateway.
                                        </p>
                                    </div>

                                    <div>
                                        <label className="block text-xs font-semibold text-slate-700 mb-1">
                                            Maksimum Percobaan Ulang (Retry Count)
                                        </label>
                                        <input
                                            type="number"
                                            min="1"
                                            max="10"
                                            value={data.max_retries}
                                            disabled={!canManage}
                                            onChange={(e) => setData('max_retries', parseInt(e.target.value) || 3)}
                                            className="w-full rounded-xl border border-slate-200 px-3.5 py-2 text-sm text-slate-800 focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500 disabled:bg-slate-50"
                                        />
                                        <p className="mt-1 text-[11px] text-slate-400">
                                            Job antrean akan mencoba ulang secara bertahap dengan backoff jika terjadi gangguan sementara.
                                        </p>
                                    </div>
                                </div>

                                {/* Webhook Callback Secret */}
                                <div className="border-t border-slate-100 pt-4">
                                    <h3 className="text-xs font-semibold text-slate-800 uppercase tracking-wider mb-2">
                                        Keamanan Webhook Callback & DLR
                                    </h3>
                                    <p className="text-xs text-slate-500 mb-3">
                                        Token rahasia untuk memvalidasi tanda tangan laporan status (DLR) dari gateway WhatsApp.
                                    </p>
                                    <div>
                                        <input
                                            type="text"
                                            value={data.webhook_secret}
                                            disabled={!canManage}
                                            onChange={(e) => setData('webhook_secret', e.target.value)}
                                            placeholder={
                                                config.has_webhook_secret
                                                    ? '•••••••• (Tersimpan, isi baru untuk mengganti)'
                                                    : 'Masukkan webhook token rahasia'
                                            }
                                            className="w-full rounded-xl border border-slate-200 px-3.5 py-2 text-sm text-slate-800 font-mono focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500 disabled:bg-slate-50"
                                        />
                                    </div>
                                </div>

                                {canManage && (
                                    <div className="pt-3 flex justify-end">
                                        <button
                                            type="submit"
                                            disabled={processing}
                                            className="inline-flex items-center gap-2 rounded-xl bg-emerald-600 px-5 py-2.5 text-sm font-semibold text-white shadow-xs hover:bg-emerald-700 focus:outline-none focus:ring-2 focus:ring-emerald-500 disabled:opacity-50 transition"
                                        >
                                            {processing ? (
                                                <RefreshCw className="h-4 w-4 animate-spin" />
                                            ) : (
                                                <Save className="h-4 w-4" />
                                            )}
                                            Simpan Pengaturan WhatsApp
                                        </button>
                                    </div>
                                )}
                            </form>
                        </div>
                    </div>

                    {/* Sidebar: OpenWA Status, Test Connection & Webhook Info (1 Col) */}
                    <div className="space-y-6">
                        {/* OpenWA Live Session Status Card */}
                        {isOpenWa && (
                            <div className="rounded-2xl border border-slate-200 bg-white p-5 shadow-xs space-y-4">
                                <div className="flex items-center justify-between">
                                    <div className="flex items-center gap-2">
                                        <Radio className="h-5 w-5 text-emerald-600" />
                                        <h2 className="text-sm font-bold text-slate-800">
                                            Status Session OpenWA
                                        </h2>
                                    </div>
                                    <span
                                        className={`inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-[11px] font-semibold border ${
                                            isSessionReady
                                                ? 'bg-emerald-50 text-emerald-700 border-emerald-200'
                                                : currentSessionStatus === 'PAIRING'
                                                ? 'bg-amber-50 text-amber-700 border-amber-200'
                                                : 'bg-slate-100 text-slate-700 border-slate-200'
                                        }`}
                                    >
                                        <span
                                            className={`h-1.5 w-1.5 rounded-full ${
                                                isSessionReady ? 'bg-emerald-500 animate-pulse' : 'bg-amber-500'
                                            }`}
                                        />
                                        {currentSessionStatus}
                                    </span>
                                </div>

                                <p className="text-xs text-slate-500 leading-relaxed">
                                    Membedakan antara respons koneksi HTTP API gateway dan status kesiapan session WhatsApp Web (ready to send).
                                </p>

                                {sessionStatusResult && (
                                    <div
                                        className={`rounded-xl border p-3 text-xs ${
                                            sessionStatusResult.is_ready
                                                ? 'bg-emerald-50 border-emerald-200 text-emerald-800'
                                                : sessionStatusResult.api_connected
                                                ? 'bg-amber-50 border-amber-200 text-amber-800'
                                                : 'bg-rose-50 border-rose-200 text-rose-800'
                                        }`}
                                    >
                                        <div className="font-semibold flex items-center gap-1.5">
                                            {sessionStatusResult.is_ready ? (
                                                <Wifi className="h-4 w-4 text-emerald-600" />
                                            ) : (
                                                <WifiOff className="h-4 w-4" />
                                            )}
                                            {sessionStatusResult.is_ready
                                                ? 'Session Terhubung & Siap Kirim'
                                                : sessionStatusResult.api_connected
                                                ? 'API Terhubung, Session Belum Siap'
                                                : 'Gagal Terhubung ke API OpenWA'}
                                        </div>
                                        <p className="mt-1 text-[11px] leading-relaxed">
                                            {sessionStatusResult.message}
                                        </p>
                                    </div>
                                )}

                                <button
                                    type="button"
                                    onClick={handleCheckSession}
                                    disabled={checkingSession || !canManage}
                                    className="w-full inline-flex items-center justify-center gap-2 rounded-xl border border-slate-300 bg-white px-4 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-emerald-500 disabled:opacity-50 transition"
                                >
                                    {checkingSession ? (
                                        <RefreshCw className="h-3.5 w-3.5 animate-spin" />
                                    ) : (
                                        <Activity className="h-3.5 w-3.5 text-emerald-600" />
                                    )}
                                    Periksa Status Session WhatsApp
                                </button>

                                {config.last_session_checked_at && (
                                    <p className="text-[11px] text-slate-400 text-center">
                                        Pengecekan terakhir: {config.last_session_checked_at}
                                    </p>
                                )}
                            </div>
                        )}

                        {/* Test Connection Card */}
                        <div className="rounded-2xl border border-slate-200 bg-white p-5 shadow-xs">
                            <div className="flex items-center gap-2 mb-3">
                                <MessageSquare className="h-5 w-5 text-emerald-600" />
                                <h2 className="text-sm font-bold text-slate-800">
                                    Uji Koneksi Pesan Teks
                                </h2>
                            </div>

                            <p className="text-xs text-slate-500 mb-4 leading-relaxed">
                                Kirim pesan teks uji coba ke nomor WhatsApp tujuan untuk memverifikasi API Key dan koneksi gateway aktif.
                            </p>

                            <div className="rounded-xl bg-blue-50 border border-blue-200 p-3 mb-4 text-xs text-blue-800 flex items-start gap-2">
                                <Info className="h-4 w-4 text-blue-600 shrink-0 mt-0.5" />
                                <p>
                                    <strong>Prinsip Keamanan:</strong> Uji coba koneksi hanya mengirimkan teks ping verifikasi. Tidak ada data gaji atau informasi rahasia karyawan yang dikirimkan.
                                </p>
                            </div>

                            <form onSubmit={handleTest} className="space-y-3">
                                <div>
                                    <label className="block text-xs font-semibold text-slate-700 mb-1">
                                        Nomor WhatsApp Uji Coba (Admin)
                                    </label>
                                    <input
                                        type="tel"
                                        value={testData.recipient}
                                        disabled={!canManage || testProcessing}
                                        onChange={(e) => setTestData('recipient', e.target.value)}
                                        placeholder="081234567890"
                                        className="w-full rounded-xl border border-slate-200 px-3 py-2 text-xs text-slate-800 placeholder-slate-400 focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500 disabled:bg-slate-50 font-mono"
                                    />
                                    {testErrors.recipient && (
                                        <p className="mt-1 text-xs text-rose-600">{testErrors.recipient}</p>
                                    )}
                                </div>

                                <button
                                    type="submit"
                                    disabled={testProcessing || !canManage}
                                    className="w-full inline-flex items-center justify-center gap-2 rounded-xl bg-slate-900 px-4 py-2.5 text-xs font-semibold text-white hover:bg-slate-800 focus:outline-none focus:ring-2 focus:ring-slate-500 disabled:opacity-50 transition"
                                >
                                    {testProcessing ? (
                                        <RefreshCw className="h-4 w-4 animate-spin" />
                                    ) : (
                                        <Send className="h-4 w-4" />
                                    )}
                                    Kirim Pesan Uji Coba
                                </button>
                            </form>

                            {/* Last Test Status Info */}
                            {config.last_tested_at && (
                                <div className="mt-4 pt-4 border-t border-slate-100 text-xs">
                                    <p className="text-slate-400">Riwayat Pengujian Terakhir:</p>
                                    <div className="mt-1.5 flex items-center justify-between">
                                        <span className="text-slate-600 font-mono text-[11px]">
                                            {config.last_tested_at}
                                        </span>
                                        <span
                                            className={`inline-flex items-center px-2 py-0.5 rounded text-[11px] font-semibold ${
                                                config.last_test_status === 'success'
                                                    ? 'bg-emerald-100 text-emerald-800'
                                                    : 'bg-rose-100 text-rose-800'
                                            }`}
                                        >
                                            {config.last_test_status === 'success' ? 'Sukses Terhubung' : 'Gagal'}
                                        </span>
                                    </div>
                                </div>
                            )}
                        </div>

                        {/* Webhook Endpoint Info */}
                        <div className="rounded-2xl border border-slate-200 bg-white p-5 shadow-xs space-y-3">
                            <h3 className="text-xs font-bold text-slate-800 uppercase tracking-wider">
                                URL Webhook Laporan Status (DLR)
                            </h3>
                            <p className="text-xs text-slate-500 leading-relaxed">
                                Daftarkan URL berikut pada panel OpenWA / Gateway agar status pengiriman terupdate otomatis menjadi &quot;Diterima&quot; (Delivered) saat ada event ack dari WhatsApp.
                            </p>
                            <div className="flex items-center gap-2">
                                <input
                                    type="text"
                                    readOnly
                                    value={config.webhook_url || ''}
                                    className="w-full rounded-xl border border-slate-200 bg-slate-50 px-3 py-1.5 text-[11px] text-slate-700 font-mono select-all focus:outline-none"
                                />
                                <button
                                    type="button"
                                    onClick={copyWebhookUrl}
                                    className="rounded-xl border border-slate-200 p-2 text-slate-600 hover:bg-slate-100 transition"
                                    title="Salin URL Webhook"
                                >
                                    {copiedWebhook ? (
                                        <CheckCircle className="h-4 w-4 text-emerald-600" />
                                    ) : (
                                        <Copy className="h-4 w-4" />
                                    )}
                                </button>
                            </div>
                        </div>

                        {/* Security Best Practices Card */}
                        <div className="rounded-2xl border border-slate-200 bg-slate-50/70 p-5 text-xs text-slate-600 space-y-2.5">
                            <div className="flex items-center gap-2 text-slate-800 font-semibold">
                                <ShieldCheck className="h-4 w-4 text-emerald-600" />
                                Kepatuhan & Keamanan Slip Gaji
                            </div>
                            <ul className="list-disc pl-4 space-y-1.5 text-slate-500">
                                <li>Slip PDF final dikirim langsung via OpenWA (Base64 atau Signed URL sementara yang aman).</li>
                                <li>Pesan pengantar tidak memuat nominal gaji untuk menjaga privasi di layar notifikasi karyawan.</li>
                                <li>Status terkirim gateway dicatat terpisah dari bukti pesan dibaca (Read).</li>
                                <li>Nomor tujuan otomatis dinormalisasi ke format internasional (628...).</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
