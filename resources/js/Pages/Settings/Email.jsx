import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, useForm, usePage } from '@inertiajs/react';
import {
    AlertCircle,
    CheckCircle,
    Eye,
    EyeOff,
    Info,
    Mail,
    RefreshCw,
    Save,
    Send,
    Server,
    ShieldCheck,
} from 'lucide-react';
import { useState } from 'react';

export default function EmailSettings({ config, canManage }) {
    const { auth, flash } = usePage().props;
    const [showPassword, setShowPassword] = useState(false);

    // Form for SMTP Configuration
    const { data, setData, put, processing, errors } = useForm({
        endpoint: config.endpoint || '',
        port: config.port || 587,
        encryption: config.encryption || 'tls',
        username: config.username || '',
        password: '',
        sender_identifier: config.sender_identifier || '',
        from_name: config.from_name || 'Personalia CV. Bina Pustaka',
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
        recipient: auth.user?.email || '',
    });

    const handleSave = (e) => {
        e.preventDefault();
        put(route('settings.email.update'), {
            preserveScroll: true,
        });
    };

    const handleTest = (e) => {
        e.preventDefault();
        postTest(route('settings.email.test'), {
            preserveScroll: true,
        });
    };

    return (
        <AuthenticatedLayout header="Pengaturan Integrasi Email SMTP">
            <Head title="Pengaturan Email SMTP" />

            <div className="max-w-5xl mx-auto space-y-6">
                {/* Header Section */}
                <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                    <div>
                        <h1 className="text-xl font-bold text-slate-900 tracking-tight flex items-center gap-2">
                            <Server className="h-6 w-6 text-indigo-600" />
                            Konfigurasi Server Email (SMTP)
                        </h1>
                        <p className="mt-1 text-xs text-slate-500">
                            Kelola parameter koneksi SMTP untuk pengiriman slip gaji berformat PDF resmi langsung ke email karyawan.
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
                            {config.is_active ? 'SMTP Aktif' : 'SMTP Nonaktif'}
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
                            <p className="font-semibold">Koneksi / Pengaturan Gagal</p>
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
                                        Parameter Server SMTP
                                    </h2>
                                    <p className="text-xs text-slate-500 mt-1">
                                        Kredensial disimpan dengan enkripsi AES-256 dan rahasia tidak pernah ditampilkan secara terbuka.
                                    </p>
                                </div>

                                {/* Active Toggle */}
                                <div className="flex items-center justify-between p-3.5 rounded-xl bg-slate-50 border border-slate-200">
                                    <div>
                                        <label htmlFor="is_active" className="text-sm font-semibold text-slate-800 block cursor-pointer">
                                            Aktifkan Pengiriman Email SMTP
                                        </label>
                                        <p className="text-xs text-slate-500">
                                            Bila aktif, pengiriman slip gaji akan menggunakan koneksi server SMTP ini.
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
                                        <div className="w-11 h-6 bg-slate-200 peer-focus:outline-none peer-focus:ring-2 peer-focus:ring-[#0B57D0] rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-[#0B57D0]"></div>
                                    </label>
                                </div>

                                {/* Host and Port */}
                                <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
                                    <div className="sm:col-span-2">
                                        <label className="block text-xs font-semibold text-slate-700 mb-1">
                                            Host / Endpoint SMTP <span className="text-rose-500">*</span>
                                        </label>
                                        <input
                                            type="text"
                                            value={data.endpoint}
                                            disabled={!canManage}
                                            onChange={(e) => setData('endpoint', e.target.value)}
                                            placeholder="contoh: mail.perusahaan.com atau smtp.sendgrid.net"
                                            className="w-full rounded-xl border border-slate-200 px-3.5 py-2 text-sm text-slate-800 placeholder-slate-400 focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500 disabled:bg-slate-50 disabled:text-slate-500"
                                        />
                                        {errors.endpoint && (
                                            <p className="mt-1 text-xs text-rose-600">{errors.endpoint}</p>
                                        )}
                                    </div>

                                    <div>
                                        <label className="block text-xs font-semibold text-slate-700 mb-1">
                                            Port <span className="text-rose-500">*</span>
                                        </label>
                                        <input
                                            type="number"
                                            value={data.port}
                                            disabled={!canManage}
                                            onChange={(e) => setData('port', parseInt(e.target.value) || '')}
                                            placeholder="587"
                                            className="w-full rounded-xl border border-slate-200 px-3.5 py-2 text-sm text-slate-800 placeholder-slate-400 focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500 disabled:bg-slate-50 disabled:text-slate-500"
                                        />
                                        {errors.port && (
                                            <p className="mt-1 text-xs text-rose-600">{errors.port}</p>
                                        )}
                                    </div>
                                </div>

                                {/* Encryption and Username */}
                                <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                    <div>
                                        <label className="block text-xs font-semibold text-slate-700 mb-1">
                                            Protokol Enkripsi <span className="text-rose-500">*</span>
                                        </label>
                                        <select
                                            value={data.encryption}
                                            disabled={!canManage}
                                            onChange={(e) => setData('encryption', e.target.value)}
                                            className="w-full rounded-xl border border-slate-200 px-3.5 py-2 text-sm text-slate-800 focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500 disabled:bg-slate-50 disabled:text-slate-500"
                                        >
                                            <option value="tls">TLS (Disarankan, Port 587)</option>
                                            <option value="ssl">SSL (Port 465)</option>
                                            <option value="none">Tanpa Enkripsi (None)</option>
                                        </select>
                                        {errors.encryption && (
                                            <p className="mt-1 text-xs text-rose-600">{errors.encryption}</p>
                                        )}
                                    </div>

                                    <div>
                                        <label className="block text-xs font-semibold text-slate-700 mb-1">
                                            Username / Akun SMTP
                                        </label>
                                        <input
                                            type="text"
                                            value={data.username}
                                            disabled={!canManage}
                                            onChange={(e) => setData('username', e.target.value)}
                                            placeholder="username atau email login"
                                            className="w-full rounded-xl border border-slate-200 px-3.5 py-2 text-sm text-slate-800 placeholder-slate-400 focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500 disabled:bg-slate-50 disabled:text-slate-500"
                                        />
                                        {errors.username && (
                                            <p className="mt-1 text-xs text-rose-600">{errors.username}</p>
                                        )}
                                    </div>
                                </div>

                                {/* Password / Secret with Masking */}
                                <div>
                                    <div className="flex items-center justify-between mb-1">
                                        <label className="block text-xs font-semibold text-slate-700">
                                            Password / Secret Token SMTP
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
                                            type={showPassword ? 'text' : 'password'}
                                            value={data.password}
                                            disabled={!canManage}
                                            onChange={(e) => setData('password', e.target.value)}
                                            placeholder={
                                                config.has_secret
                                                    ? '•••••••• (Biarkan kosong jika tidak ingin mengubah)'
                                                    : 'Masukkan password server SMTP'
                                            }
                                            className="w-full rounded-xl border border-slate-200 pl-3.5 pr-10 py-2 text-sm text-slate-800 placeholder-slate-400 focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500 disabled:bg-slate-50 disabled:text-slate-500 font-mono"
                                        />
                                        <button
                                            type="button"
                                            onClick={() => setShowPassword(!showPassword)}
                                            className="absolute right-3 top-2.5 text-slate-400 hover:text-slate-600"
                                            aria-label="Tampilkan atau sembunyikan password"
                                        >
                                            {showPassword ? (
                                                <EyeOff className="h-4 w-4" />
                                            ) : (
                                                <Eye className="h-4 w-4" />
                                            )}
                                        </button>
                                    </div>
                                    <p className="mt-1 text-[11px] text-slate-400">
                                        Password tidak pernah dikirim balik ke browser. Masukkan nilai baru hanya jika ingin mengganti.
                                    </p>
                                    {errors.password && (
                                        <p className="mt-1 text-xs text-rose-600">{errors.password}</p>
                                    )}
                                </div>

                                <div className="border-t border-slate-100 pt-4">
                                    <h3 className="text-xs font-semibold text-slate-800 uppercase tracking-wider mb-3">
                                        Identitas Pengirim (Sender Identity)
                                    </h3>

                                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                        <div>
                                            <label className="block text-xs font-semibold text-slate-700 mb-1">
                                                Alamat Email Pengirim (From Email) <span className="text-rose-500">*</span>
                                            </label>
                                            <input
                                                type="email"
                                                value={data.sender_identifier}
                                                disabled={!canManage}
                                                onChange={(e) => setData('sender_identifier', e.target.value)}
                                                placeholder="no-reply@perusahaan.com"
                                                className="w-full rounded-xl border border-slate-200 px-3.5 py-2 text-sm text-slate-800 placeholder-slate-400 focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500 disabled:bg-slate-50 disabled:text-slate-500"
                                            />
                                            {errors.sender_identifier && (
                                                <p className="mt-1 text-xs text-rose-600">{errors.sender_identifier}</p>
                                            )}
                                        </div>

                                        <div>
                                            <label className="block text-xs font-semibold text-slate-700 mb-1">
                                                Nama Pengirim (From Name) <span className="text-rose-500">*</span>
                                            </label>
                                            <input
                                                type="text"
                                                value={data.from_name}
                                                disabled={!canManage}
                                                onChange={(e) => setData('from_name', e.target.value)}
                                                placeholder="Personalia CV. Bina Pustaka"
                                                className="w-full rounded-xl border border-slate-200 px-3.5 py-2 text-sm text-slate-800 placeholder-slate-400 focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500 disabled:bg-slate-50 disabled:text-slate-500"
                                            />
                                            {errors.from_name && (
                                                <p className="mt-1 text-xs text-rose-600">{errors.from_name}</p>
                                            )}
                                        </div>
                                    </div>
                                </div>

                                {canManage && (
                                    <div className="pt-3 flex justify-end">
                                        <button
                                            type="submit"
                                            disabled={processing}
                                            className="inline-flex items-center gap-2 rounded-xl bg-[#0B57D0] px-5 py-2.5 text-xs font-semibold text-white shadow-xs hover:bg-[#0A58CA] focus:outline-none focus:ring-2 focus:ring-[#0B57D0] disabled:opacity-50 transition"
                                        >
                                            {processing ? (
                                                <RefreshCw className="h-4 w-4 animate-spin" />
                                            ) : (
                                                <Save className="h-4 w-4" />
                                            )}
                                            Simpan Konfigurasi SMTP
                                        </button>
                                    </div>
                                )}
                            </form>
                        </div>
                    </div>

                    {/* Sidebar / Test Connection Panel (1 Col) */}
                    <div className="space-y-6">
                        {/* Test Connection Card */}
                        <div className="rounded-2xl border border-slate-200 bg-white p-5 shadow-xs">
                            <div className="flex items-center gap-2 mb-3">
                                <Mail className="h-5 w-5 text-indigo-600" />
                                <h2 className="text-sm font-bold text-slate-800">
                                    Uji Koneksi Server
                                </h2>
                            </div>

                            <p className="text-xs text-slate-500 mb-4 leading-relaxed">
                                Kirimkan pesan uji koneksi sederhana ke email penerima untuk memastikan konfigurasi host, port, dan kredensial valid.
                            </p>

                            <div className="rounded-xl bg-blue-50 border border-blue-200 p-3 mb-4 text-xs text-blue-800 flex items-start gap-2">
                                <Info className="h-4 w-4 text-blue-600 shrink-0 mt-0.5" />
                                <p>
                                    <strong>Prinsip Keamanan:</strong> Uji coba ini hanya mengirimkan ping koneksi teks polos tanpa data gaji, nominal, atau rincian slip karyawan mana pun.
                                </p>
                            </div>

                            <form onSubmit={handleTest} className="space-y-3">
                                <div>
                                    <label className="block text-xs font-semibold text-slate-700 mb-1">
                                        Email Tujuan Uji Coba
                                    </label>
                                    <input
                                        type="email"
                                        value={testData.recipient}
                                        disabled={!canManage || testProcessing}
                                        onChange={(e) => setTestData('recipient', e.target.value)}
                                        placeholder="nama@email.com"
                                        className="w-full rounded-xl border border-slate-200 px-3 py-2 text-xs text-slate-800 placeholder-slate-400 focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500 disabled:bg-slate-50"
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
                                    Kirim Email Uji Coba
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

                        {/* Security Best Practices Card */}
                        <div className="rounded-2xl border border-slate-200 bg-slate-50/70 p-5 text-xs text-slate-600 space-y-2.5">
                            <div className="flex items-center gap-2 text-slate-800 font-semibold">
                                <ShieldCheck className="h-4 w-4 text-emerald-600" />
                                Kepatuhan & Privasi
                            </div>
                            <ul className="list-disc pl-4 space-y-1.5 text-slate-500">
                                <li>Pengiriman dilakukan secara individual per karyawan tanpa CC atau BCC massal.</li>
                                <li>Hanya slip berstatus Final yang diizinkan untuk dikirimkan melalui sistem.</li>
                                <li>Password dan secret SMTP dienkripsi pada database dan tidak dicatat ke dalam log audit.</li>
                                <li>Lampiran PDF dilindungi dengan hashing integritas dokumen.</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
