import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import TextInput from '@/Components/TextInput';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft, Calendar, CalendarPlus, HelpCircle } from 'lucide-react';

export default function PayrollPeriodsCreate({ suggestedCode, suggestedStart, suggestedEnd }) {
    const { data, setData, post, processing, errors } = useForm({
        period_code: suggestedCode || '',
        start_date: suggestedStart || '',
        end_date: suggestedEnd || '',
        approval_comment: '',
    });

    const submit = (e) => {
        e.preventDefault();
        post(route('payroll-periods.store'));
    };

    return (
        <AuthenticatedLayout header="Buka Periode Penggajian Baru">
            <Head title="Buka Periode Gaji" />

            <div className="max-w-3xl mx-auto space-y-6">
                {/* Navigation Back */}
                <div className="flex items-center gap-3">
                    <Link
                        href={route('payroll-periods.index')}
                        className="inline-flex items-center gap-1.5 text-xs font-semibold text-slate-500 hover:text-slate-900 transition"
                    >
                        <ArrowLeft className="h-4 w-4" />
                        <span>Kembali ke Daftar Periode</span>
                    </Link>
                </div>

                {/* Form Card */}
                <div className="rounded-2xl border border-slate-200 bg-white p-6 sm:p-8 shadow-xs">
                    <div className="border-b border-slate-100 pb-5">
                        <div className="flex items-center gap-2 text-indigo-600 font-semibold text-xs uppercase tracking-wider">
                            <CalendarPlus className="h-4 w-4" />
                            <span>Formulir Periode Gaji</span>
                        </div>
                        <h1 className="mt-1 text-xl font-bold text-slate-900">
                            Buka Siklus Periode Penggajian
                        </h1>
                        <p className="mt-1 text-xs text-slate-500">
                            Tentukan kode unik periode dan rentang tanggal kerja. Setelah dibuat, slip gaji karyawan dapat diinputkan secara manual ke dalam periode ini.
                        </p>
                    </div>

                    <form onSubmit={submit} className="mt-6 space-y-6">
                        {/* Period Code */}
                        <div>
                            <InputLabel htmlFor="period_code" value="Kode Unik Periode *" />
                            <TextInput
                                id="period_code"
                                type="text"
                                value={data.period_code}
                                onChange={(e) => setData('period_code', e.target.value)}
                                className="mt-1 block w-full text-xs font-mono"
                                placeholder="Contoh: 2026-10 atau 2026-THR"
                                required
                                isFocused
                            />
                            <p className="mt-1 text-[11px] text-slate-400">
                                Disarankan format YYYY-MM (misal 2026-10). Wajib unik dan tidak boleh sama dengan periode terdahulu.
                            </p>
                            <InputError message={errors.period_code} className="mt-1" />
                        </div>

                        {/* Date Range */}
                        <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <InputLabel htmlFor="start_date" value="Tanggal Awal Periode *" />
                                <TextInput
                                    id="start_date"
                                    type="date"
                                    value={data.start_date}
                                    onChange={(e) => setData('start_date', e.target.value)}
                                    className="mt-1 block w-full text-xs"
                                    required
                                />
                                <InputError message={errors.start_date} className="mt-1" />
                            </div>

                            <div>
                                <InputLabel htmlFor="end_date" value="Tanggal Akhir Periode *" />
                                <TextInput
                                    id="end_date"
                                    type="date"
                                    value={data.end_date}
                                    onChange={(e) => setData('end_date', e.target.value)}
                                    className="mt-1 block w-full text-xs"
                                    required
                                />
                                <InputError message={errors.end_date} className="mt-1" />
                            </div>
                        </div>

                        {/* Note / Comment */}
                        <div>
                            <InputLabel htmlFor="approval_comment" value="Catatan / Keterangan (Opsional)" />
                            <textarea
                                id="approval_comment"
                                rows={3}
                                value={data.approval_comment}
                                onChange={(e) => setData('approval_comment', e.target.value)}
                                className="mt-1 block w-full rounded-md border-slate-300 shadow-xs focus:border-indigo-600 focus:ring-indigo-600 text-xs"
                                placeholder="Contoh: Penggajian reguler bulan Oktober 2026 dengan penyesuaian tunjangan operasional."
                            />
                            <InputError message={errors.approval_comment} className="mt-1" />
                        </div>

                        {/* Submit Actions */}
                        <div className="mt-8 flex items-center justify-end gap-3 border-t border-slate-100 pt-5">
                            <Link
                                href={route('payroll-periods.index')}
                                className="inline-flex items-center rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-xs font-semibold text-slate-700 hover:bg-slate-50 transition"
                            >
                                Batal
                            </Link>
                            <PrimaryButton disabled={processing} className="rounded-xl">
                                {processing ? 'Memproses...' : 'Buat Periode Gaji'}
                            </PrimaryButton>
                        </div>
                    </form>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
