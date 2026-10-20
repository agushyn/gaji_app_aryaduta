import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';
import TextInput from '@/Components/TextInput';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, useForm } from '@inertiajs/react';
import {
    AlertCircle,
    ArrowLeft,
    CheckCircle2,
    Clock,
    History,
    Save,
    ShieldAlert,
} from 'lucide-react';
import { useState } from 'react';
import FormulaBuilder from './FormulaBuilder';

export default function PayrollConfigurationsEdit({
    config,
    versions,
    payComponents,
    variableLabels,
}) {
    const { data, setData, put, processing, errors } = useForm({
        name: config.name,
        code: config.code,
        component_id: config.component_id,
        day_type: config.day_type,
        is_active: config.is_active,
        formula: config.formula,
        description: `Pembaruan dari versi ${config.current_version}`,
    });

    const [activeTab, setActiveTab] = useState('formula'); // 'formula' | 'history'

    // Safe formatter to prevent 'Objects are not valid as a React child'
    const formatFormulaPreviewText = (preview) => {
        if (!preview) return '-';
        if (typeof preview === 'string') return preview;
        if (typeof preview === 'object') {
            if (preview.text) return String(preview.text);
            if (preview.formatted_text) return String(preview.formatted_text);
            if (preview.formula_text) return String(preview.formula_text);
            if (preview.regular_overtime || preview.holiday_overtime) {
                const parts = [];
                if (preview.regular_overtime) {
                    const reg = preview.regular_overtime;
                    parts.push(typeof reg === 'string' ? reg : (reg.text || JSON.stringify(reg, null, 2)));
                }
                if (preview.holiday_overtime) {
                    const hol = preview.holiday_overtime;
                    parts.push(typeof hol === 'string' ? hol : (hol.text || JSON.stringify(hol, null, 2)));
                }
                return parts.join('\n\n---\n\n');
            }
            return JSON.stringify(preview, null, 2);
        }
        return String(preview);
    };

    const handleSubmit = (e) => {
        e.preventDefault();
        put(route('payroll-configurations.update', config.id));
    };

    return (
        <AuthenticatedLayout
            title={`Edit Konfigurasi - ${config.name}`}
            header={`Edit Konfigurasi: ${config.name}`}
        >
            <Head title={`Edit Konfigurasi - ${config.name}`} />

            <div className="max-w-4xl mx-auto space-y-6">
                {/* Header Action Bar */}
                <div className="flex items-center gap-3">
                    <Link
                        href={route('payroll-configurations.index')}
                        className="p-2 rounded-xl bg-white border border-slate-200 text-slate-600 hover:bg-slate-50 transition shadow-xs"
                        title="Kembali ke Daftar Konfigurasi"
                    >
                        <ArrowLeft className="h-5 w-5" />
                    </Link>
                    <div>
                        <div className="flex items-center gap-2">
                            <h1 className="text-xl font-bold text-slate-900 tracking-tight">
                                Edit Konfigurasi: {config.name}
                            </h1>
                            <span className="px-2.5 py-0.5 bg-purple-100 text-purple-700 font-mono text-xs font-bold rounded-full border border-purple-200">
                                v{config.current_version}
                            </span>
                        </div>
                        <p className="text-xs text-slate-500 mt-0.5">
                            Setiap perubahan rumus akan otomatis membuat versi baru guna menjaga integritas audit payroll finalized.
                        </p>
                    </div>
                </div>
                {/* Navigation Tabs */}
                <div className="flex space-x-2 border-b border-slate-200 pb-2">
                    <button
                        type="button"
                        onClick={() => setActiveTab('formula')}
                        className={`px-4 py-2 text-xs font-semibold rounded-lg transition ${
                            activeTab === 'formula'
                                ? 'bg-indigo-600 text-white'
                                : 'bg-slate-100 text-slate-600 hover:bg-slate-200'
                        }`}
                    >
                        Pengaturan & Formula
                    </button>
                    <button
                        type="button"
                        onClick={() => setActiveTab('history')}
                        className={`inline-flex items-center px-4 py-2 text-xs font-semibold rounded-lg transition ${
                            activeTab === 'history'
                                ? 'bg-indigo-600 text-white'
                                : 'bg-slate-100 text-slate-600 hover:bg-slate-200'
                        }`}
                    >
                        <History className="w-3.5 h-3.5 mr-1.5" />
                        Riwayat Versi ({versions.length})
                    </button>
                </div>

                {activeTab === 'formula' ? (
                    <form onSubmit={handleSubmit} className="space-y-6">
                        {/* Notice Banner */}
                        <div className="bg-amber-50 border border-amber-200 rounded-xl p-4 flex items-start space-x-3 text-xs text-amber-800">
                            <AlertCircle className="w-4 h-4 text-amber-600 mt-0.5 flex-shrink-0" />
                            <div>
                                <span className="font-semibold">Prinsip Immutability Snapshot:</span> Jika Anda mengubah rumus atau nilai multiplier, sistem akan otomatis menaikkan versi menjadi <strong>v{config.current_version + 1}</strong>. Payroll yang sudah finalized di masa lalu akan tetap menggunakan versi rumus sebelumnya sehingga hasil audit tidak berubah.
                            </div>
                        </div>

                        {/* Basic Info */}
                        <div className="bg-white p-6 rounded-xl border border-slate-200 shadow-sm space-y-4">
                            <h3 className="text-sm font-bold text-slate-800 border-b border-slate-100 pb-3">
                                Parameter Konfigurasi
                            </h3>

                            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <InputLabel htmlFor="name" value="Nama Konfigurasi *" />
                                    <TextInput
                                        id="name"
                                        type="text"
                                        className="mt-1 block w-full text-xs"
                                        value={data.name}
                                        onChange={(e) => setData('name', e.target.value)}
                                        required
                                    />
                                    <InputError message={errors.name} className="mt-1" />
                                </div>

                                <div>
                                    <InputLabel htmlFor="component_id" value="Komponen Gaji *" />
                                    <select
                                        id="component_id"
                                        value={data.component_id}
                                        onChange={(e) => setData('component_id', e.target.value)}
                                        className="mt-1 block w-full text-xs rounded-lg border-slate-300 focus:border-indigo-500 focus:ring-indigo-500"
                                        required
                                    >
                                        <option value="">Pilih Komponen Gaji</option>
                                        {payComponents.map((comp) => (
                                            <option key={comp.id} value={comp.id}>
                                                {comp.name} ({comp.code})
                                            </option>
                                        ))}
                                    </select>
                                    <InputError message={errors.component_id} className="mt-1" />
                                </div>

                                <div>
                                    <InputLabel htmlFor="day_type" value="Jenis Hari *" />
                                    <select
                                        id="day_type"
                                        value={data.day_type}
                                        onChange={(e) => setData('day_type', e.target.value)}
                                        className="mt-1 block w-full text-xs rounded-lg border-slate-300 focus:border-indigo-500 focus:ring-indigo-500"
                                        required
                                    >
                                        <option value="working_day">Hari Biasa / Hari Kerja</option>
                                        <option value="holiday">Hari Libur (Nasional / Cuti Bersama)</option>
                                        <option value="national_holiday">Hari Libur Nasional</option>
                                        <option value="joint_leave">Cuti Bersama</option>
                                        <option value="weekend">Weekend</option>
                                    </select>
                                    <InputError message={errors.day_type} className="mt-1" />
                                </div>

                                <div>
                                    <InputLabel htmlFor="description" value="Catatan / Deskripsi Perubahan" />
                                    <TextInput
                                        id="description"
                                        type="text"
                                        className="mt-1 block w-full text-xs"
                                        value={data.description}
                                        onChange={(e) => setData('description', e.target.value)}
                                        placeholder="Alasan perubahan versi"
                                    />
                                    <InputError message={errors.description} className="mt-1" />
                                </div>
                            </div>

                            <div className="pt-2 flex items-center space-x-2">
                                <input
                                    type="checkbox"
                                    id="is_active"
                                    checked={data.is_active}
                                    onChange={(e) => setData('is_active', e.target.checked)}
                                    className="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500"
                                />
                                <label htmlFor="is_active" className="text-xs font-medium text-slate-700 cursor-pointer">
                                    Konfigurasi aktif (digunakan untuk periode payroll berikutnya)
                                </label>
                            </div>
                        </div>

                        {/* Interactive Formula Builder & Simulator */}
                        <div className="bg-white p-6 rounded-xl border border-slate-200 shadow-sm space-y-4">
                            <div className="flex items-center justify-between border-b border-slate-100 pb-3">
                                <h3 className="text-sm font-bold text-slate-800">
                                    Formula Builder & Multiplier
                                </h3>
                                <span className="text-xs text-slate-500">
                                    Tarif dasar dari Master Karyawan
                                </span>
                            </div>

                            <FormulaBuilder
                                formula={data.formula}
                                onChange={(newAst) => setData('formula', newAst)}
                                dayType={data.day_type}
                            />

                            <InputError message={errors.formula} className="mt-2" />
                        </div>

                        {/* Submit */}
                        <div className="flex items-center justify-end space-x-3 pt-2">
                            <Link
                                href={route('payroll-configurations.index')}
                                className="px-4 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-100 rounded-lg transition"
                            >
                                Batal
                            </Link>
                            <PrimaryButton disabled={processing} className="text-xs">
                                <Save className="w-4 h-4 mr-1.5" />
                                {processing ? 'Menyimpan...' : 'Perbarui Konfigurasi'}
                            </PrimaryButton>
                        </div>
                    </form>
                ) : (
                    /* History Tab */
                    <div className="bg-white rounded-xl border border-slate-200 shadow-sm p-6 space-y-4">
                        <div className="flex items-center justify-between border-b border-slate-200 pb-3">
                            <h3 className="font-bold text-slate-800 text-sm">
                                Riwayat Versi Formula ({config.name})
                            </h3>
                            <span className="text-xs text-slate-500">
                                Menampilkan seluruh evolusi rumus
                            </span>
                        </div>

                        <div className="space-y-4">
                            {versions.map((ver) => (
                                <div
                                    key={ver.id}
                                    className={`p-4 rounded-xl border ${
                                        ver.version === config.current_version
                                            ? 'border-indigo-300 bg-indigo-50/40'
                                            : 'border-slate-200 bg-slate-50'
                                    }`}
                                >
                                    <div className="flex items-center justify-between mb-2">
                                        <div className="flex items-center space-x-2">
                                            <span className="px-2.5 py-0.5 rounded font-mono text-xs font-bold bg-purple-100 text-purple-800">
                                                Versi {ver.version}
                                            </span>
                                            {ver.version === config.current_version && (
                                                <span className="inline-flex items-center text-[11px] font-semibold text-emerald-700 bg-emerald-100 px-2 py-0.5 rounded-full">
                                                    <CheckCircle2 className="w-3 h-3 mr-1" />
                                                    Versi Aktif Saat Ini
                                                </span>
                                            )}
                                        </div>
                                        <div className="flex items-center space-x-1 text-[11px] text-slate-500">
                                            <Clock className="w-3 h-3" />
                                            <span>{ver.created_at}</span>
                                        </div>
                                    </div>

                                    {ver.description && (
                                        <p className="text-xs text-slate-600 mb-2 italic">
                                            {ver.description}
                                        </p>
                                    )}

                                    <div className="bg-slate-900 text-indigo-100 p-3 rounded-lg text-xs font-mono whitespace-pre-wrap">
                                        {formatFormulaPreviewText(ver.preview)}
                                    </div>
                                </div>
                            ))}
                        </div>
                    </div>
                )}
            </div>
        </AuthenticatedLayout>
    );
}
