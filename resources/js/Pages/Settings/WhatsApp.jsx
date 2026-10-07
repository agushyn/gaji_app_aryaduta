import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, useForm, usePage } from '@inertiajs/react';
import {
    AlertCircle,
    CheckCircle,
    Copy,
    Eye,
    EyeOff,
    Info,
    MessageSquare,
    RefreshCw,
    Save,
    Send,
    ShieldCheck,
    Smartphone,
} from 'lucide-react';
import { useState } from 'react';

export default function WhatsAppSettings({ config, canManage }) {
    const { auth, flash } = usePage().props;
    const [showSecret, setShowSecret] = useState(false);
    const [copiedWebhook, setCopiedWebhook] = useState(false);

    // Form for WhatsApp Gateway Configuration
    const { data, setData, put, processing, errors } = useForm({
        driver: config.driver || 'fonnte',
        endpoint: config.endpoint || 'https://api.fonnte.com/send',
        sender_identifier: config.sender_identifier || '',
        secret: '',
        webhook_secret: '',
        rate_limit_per_minute: config.rate_limit_per_minute || 30,
        timeout: config.timeout || 25,
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

    const copyWebhookUrl = () => {
        if (config.webhook_url) {
            navigator.clipboard.writeText(config.webhook_url);
            setCopiedWebhook(true);
            setTimeout(() => setCopiedWebhook(false), 2000);
        }
    };

    return (
        <AuthenticatedLayout header="Pengaturan WhatsApp Gateway">
            <Head title="Pengaturan WhatsApp" />

            <div className="max-w-5xl mx-auto space-y-6">
                {/* Header Section */}
                <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                    <div>
                        <h1 className="text-xl font-bold text-slate-900 tracking-tight flex items-center gap-2">
                            <Smartphone className="h-6 w-6 text-emerald-600" />
                            Konfigurasi Gateway WhatsApp Pihak Ketiga
                        </h1>
                        <p className="mt-1 text-xs text-slate-500">
                            Kelola adapter gateway WhatsApp untuk pengiriman dokumen slip gaji PDF secara terenkripsi dan terlacak.
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
                                        Sistem menggunakan adapter interface agar vendor gateway dapat diganti tanpa merombak logika bisnis.
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

                                {/* Driver and Device Sender */}
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
                                                setData((prev) => ({
                                                    ...prev,
                                                    driver: drv,
                                                    endpoint:
                                                        drv === 'fonnte'
                                                            ? 'https://api.fonnte.com/send'
                                                            : prev.endpoint,
                                                }));
                                            }}
                                            className="w-full rounded-xl border border-slate-200 px-3.5 py-2 text-sm text-slate-800 focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500 disabled:bg-slate-50 disabled:text-slate-500"
                                        >
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
                                            Sender ID / Device Token
                                        </label>
                                        <input
                                            type="text"
                                            value={data.sender_identifier}
                                            disabled={!canManage}
                                            onChange={(e) => setData('sender_identifier', e.target.value)}
                                            placeholder="contoh: device-01 atau nomor pengirim"
                                            className="w-full rounded-xl border border-slate-200 px-3.5 py-2 text-sm text-slate-800 placeholder-slate-400 focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500 disabled:bg-slate-50 disabled:text-slate-500"
                                        />
                                        {errors.sender_identifier && (
                                            <p className="mt-1 text-xs text-rose-600">{errors.sender_identifier}</p>
                                        )}
                                    </div>
                                </div>

                                {/* Endpoint URL */}
                                <div>
                                    <label className="block text-xs font-semibold text-slate-700 mb-1">
                                        URL Endpoint API Gateway
                                    </label>
                                    <input
                                        type="url"
                                        value={data.endpoint}
                                        disabled={!canManage}
                                        onChange={(e) => setData('endpoint', e.target.value)}
                                        placeholder="https://api.fonnte.com/send"
                                        className="w-full rounded-xl border border-slate-200 px-3.5 py-2 text-sm text-slate-800 placeholder-slate-400 focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500 disabled:bg-slate-50 disabled:text-slate-500"
                                    />
                                    {errors.endpoint && (
                                        <p className="mt-1 text-xs text-rose-600">{errors.endpoint}</p>
                                    )}
                                </div>

                                {/* Secret Token with Masking */}
                                <div>
                                    <div className="flex items-center justify-between mb-1">
                                        <label className="block text-xs font-semibold text-slate-700">
                                            Token Rahasia / API Secret
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
                                                    : 'Masukkan API Token dari penyedia WhatsApp'
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
                                        Token dienkripsi AES-256 dan tidak pernah dikirimkan mentah ke browser.
                                    </p>
                                    {errors.secret && (
                                        <p className="mt-1 text-xs text-rose-600">{errors.secret}</p>
                                    )}
                                </div>

                                {/* Rate Limiting & Timeout */}
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
                                            Mencegah pemblokiran nomor oleh WhatsApp akibat pengiriman terlalu cepat.
                                        </p>
                                    </div>

                                    <div>
                                        <label className="block text-xs font-semibold text-slate-700 mb-1">
                                            Waktu Batas Koneksi (Timeout Detik)
                                        </label>
                                        <input
                                            type="number"
                                            min="5"
                                            max="60"
                                            value={data.timeout}
                                            disabled={!canManage}
                                            onChange={(e) => setData('timeout', parseInt(e.target.value) || 25)}
                                            className="w-full rounded-xl border border-slate-200 px-3.5 py-2 text-sm text-slate-800 focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500 disabled:bg-slate-50"
                                        />
                                    </div>
                                </div>

                                {/* Webhook Callback Secret */}
                                <div className="border-t border-slate-100 pt-4">
                                    <h3 className="text-xs font-semibold text-slate-800 uppercase tracking-wider mb-2">
                                        Keamanan Webhook Callback
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

                    {/* Sidebar: Test Connection & Webhook Info (1 Col) */}
                    <div className="space-y-6">
                        {/* Test Connection Card */}
                        <div className="rounded-2xl border border-slate-200 bg-white p-5 shadow-xs">
                            <div className="flex items-center gap-2 mb-3">
                                <MessageSquare className="h-5 w-5 text-emerald-600" />
                                <h2 className="text-sm font-bold text-slate-800">
                                    Uji Koneksi Gateway
                                </h2>
                            </div>

                            <p className="text-xs text-slate-500 mb-4 leading-relaxed">
                                Kirim pesan teks uji coba ke nomor WhatsApp tujuan untuk memverifikasi token dan koneksi server.
                            </p>

                            <div className="rounded-xl bg-blue-50 border border-blue-200 p-3 mb-4 text-xs text-blue-800 flex items-start gap-2">
                                <Info className="h-4 w-4 text-blue-600 shrink-0 mt-0.5" />
                                <p>
                                    <strong>Prinsip Keamanan:</strong> Uji coba koneksi hanya mengirimkan pesan ping verifikasi sederhana. Tidak ada data gaji atau informasi rahasia karyawan yang dikirimkan.
                                </p>
                            </div>

                            <form onSubmit={handleTest} className="space-y-3">
                                <div>
                                    <label className="block text-xs font-semibold text-slate-700 mb-1">
                                        Nomor WhatsApp Uji Coba
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
                                Daftarkan URL berikut pada panel kontrol provider WhatsApp Anda agar status pengiriman terupdate otomatis menjadi &quot;Diterima&quot; (Delivered).
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
                                Kepatuhan & Privasi
                            </div>
                            <ul className="list-disc pl-4 space-y-1.5 text-slate-500">
                                <li>Dokumen PDF dilampirkan langsung atau melalui URL privat yang kedaluwarsa secara otomatis.</li>
                                <li>Pesan teks tidak memuat rincian nominal gaji guna melindungi privasi di layar notifikasi ponsel.</li>
                                <li>Status terkirim tidak otomatis dianggap &quot;Diterima&quot; sebelum bukti DLR diterima dari provider.</li>
                                <li>Nomor telepon dinormalisasi ke format internasional standar (628...).</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
