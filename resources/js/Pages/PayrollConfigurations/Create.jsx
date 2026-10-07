import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';
import TextInput from '@/Components/TextInput';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft, Save, Sparkles } from 'lucide-react';
import { useState } from 'react';
import FormulaBuilder from './FormulaBuilder';

export default function PayrollConfigurationsCreate({
    payComponents,
    defaultWorkingDayFormula,
    defaultHolidayFormula,
    variableLabels,
}) {
    const defaultComponent = payComponents.find((c) => c.code === 'OVERTIME') || payComponents[0];

    const { data, setData, post, processing, errors } = useForm({
        name: 'Lembur Hari Biasa',
        code: '',
        component_id: defaultComponent ? defaultComponent.id : '',
        day_type: 'working_day',
        is_active: true,
        formula: defaultWorkingDayFormula,
        description: 'Konfigurasi aturan lembur baru.',
    });

    const handleDayTypeChange = (newDayType) => {
        setData((prev) => {
            const isHoliday = newDayType === 'holiday' || newDayType === 'national_holiday' || newDayType === 'joint_leave';
            return {
                ...prev,
                day_type: newDayType,
                formula: isHoliday ? defaultHolidayFormula : defaultWorkingDayFormula,
                name: prev.name === 'Lembur Hari Biasa' && isHoliday
                    ? 'Lembur Hari Libur'
                    : prev.name === 'Lembur Hari Libur' && !isHoliday
                    ? 'Lembur Hari Biasa'
                    : prev.name,
            };
        });
    };

    const handleSubmit = (e) => {
        e.preventDefault();
        post(route('payroll-configurations.store'));
    };

    return (
        <AuthenticatedLayout
            title="Tambah Konfigurasi Perhitungan Payroll"
            header={
                <div className="flex items-center justify-between">
                    <div className="flex items-center space-x-3">
                        <Link
                            href={route('payroll-configurations.index')}
                            className="p-1.5 text-slate-500 hover:text-slate-800 bg-white border border-slate-200 rounded-lg transition"
                        >
                            <ArrowLeft className="w-4 h-4" />
                        </Link>
                        <div>
                            <h2 className="text-xl font-bold text-slate-800">
                                Buat Konfigurasi Perhitungan Payroll
                            </h2>
                            <p className="text-xs text-slate-500">
                                Buat aturan perhitungan formula baru untuk komponen penggajian.
                            </p>
                        </div>
                    </div>
                </div>
            }
        >
            <Head title="Tambah Konfigurasi Payroll" />

            <div className="max-w-4xl mx-auto">
                <form onSubmit={handleSubmit} className="space-y-6">
                    {/* General Configuration Card */}
                    <div className="bg-white p-6 rounded-xl border border-slate-200 shadow-sm space-y-4">
                        <h3 className="text-sm font-bold text-slate-800 border-b border-slate-100 pb-3">
                            Informasi Dasar Konfigurasi
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
                                    placeholder="Contoh: Lembur Hari Biasa"
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
                                    onChange={(e) => handleDayTypeChange(e.target.value)}
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
                                <InputLabel htmlFor="description" value="Deskripsi Konfigurasi" />
                                <TextInput
                                    id="description"
                                    type="text"
                                    className="mt-1 block w-full text-xs"
                                    value={data.description}
                                    onChange={(e) => setData('description', e.target.value)}
                                    placeholder="Penjelasan aturan perhitungan"
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
                                Aktifkan konfigurasi ini untuk perhitungan payroll berikutnya
                            </label>
                        </div>
                    </div>

                    {/* Interactive Formula Builder & Simulator Card */}
                    <div className="bg-white p-6 rounded-xl border border-slate-200 shadow-sm space-y-4">
                        <div className="flex items-center justify-between border-b border-slate-100 pb-3">
                            <h3 className="text-sm font-bold text-slate-800">
                                Desain Alur Rumus & Multiplier
                            </h3>
                            <span className="text-xs text-slate-500">
                                Tarif default diambil otomatis dari Master Karyawan
                            </span>
                        </div>

                        <FormulaBuilder
                            formula={data.formula}
                            onChange={(newAst) => setData('formula', newAst)}
                            dayType={data.day_type}
                        />

                        <InputError message={errors.formula} className="mt-2" />
                    </div>

                    {/* Actions Card */}
                    <div className="flex items-center justify-end space-x-3 pt-2">
                        <Link
                            href={route('payroll-configurations.index')}
                            className="px-4 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-100 rounded-lg transition"
                        >
                            Batal
                        </Link>
                        <PrimaryButton disabled={processing} className="text-xs">
                            <Save className="w-4 h-4 mr-1.5" />
                            {processing ? 'Menyimpan...' : 'Simpan Konfigurasi (Versi 1)'}
                        </PrimaryButton>
                    </div>
                </form>
            </div>
        </AuthenticatedLayout>
    );
}
