import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import TextInput from '@/Components/TextInput';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft, Calendar, Edit3 } from 'lucide-react';

export default function PayrollPeriodsEdit({ period }) {
    const { data, setData, put, processing, errors } = useForm({
        period_code: period.period_code || '',
        start_date: period.start_date ? period.start_date.substring(0, 10) : '',
        end_date: period.end_date ? period.end_date.substring(0, 10) : '',
        approval_comment: period.approval_comment || '',
    });

    const submit = (e) => {
        e.preventDefault();
        put(route('payroll-periods.update', period.id));
    };

    return (
        <AuthenticatedLayout header={`Ubah Periode: ${period.period_code}`}>
            <Head title={`Edit Periode - ${period.period_code}`} />

            <div className="max-w-3xl mx-auto space-y-6">
                {/* Navigation Back */}
                <div className="flex items-center gap-3">
                    <Link
                        href={route('payroll-periods.show', period.id)}
                        className="inline-flex items-center gap-1.5 text-xs font-semibold text-slate-500 hover:text-slate-900 transition"
                    >
                        <ArrowLeft className="h-4 w-4" />
                        <span>Kembali ke Detail Periode</span>
                    </Link>
                </div>

                {/* Form Card */}
                <div className="rounded-2xl border border-slate-200 bg-white p-6 sm:p-8 shadow-xs">
                    <div className="border-b border-slate-100 pb-5">
                        <div className="flex items-center gap-2 text-indigo-600 font-semibold text-xs uppercase tracking-wider">
                            <Edit3 className="h-4 w-4" />
                            <span>Edit Periode Gaji</span>
                        </div>
                        <h1 className="mt-1 text-xl font-bold text-slate-900">
                            Perbarui Data Periode Penggajian
                        </h1>
                        <p className="mt-1 text-xs text-slate-500">
                            Perubahan rentang tanggal tidak akan mengubah snapshot tanggal pada slip gaji yang telah difinalisasi.
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
                                required
                            />
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
                            <InputLabel htmlFor="approval_comment" value="Catatan / Keterangan" />
                            <textarea
                                id="approval_comment"
                                rows={3}
                                value={data.approval_comment}
                                onChange={(e) => setData('approval_comment', e.target.value)}
                                className="mt-1 block w-full rounded-md border-slate-300 shadow-xs focus:border-indigo-600 focus:ring-indigo-600 text-xs"
                            />
                            <InputError message={errors.approval_comment} className="mt-1" />
                        </div>

                        {/* Submit Actions */}
                        <div className="mt-8 flex items-center justify-end gap-3 border-t border-slate-100 pt-5">
                            <Link
                                href={route('payroll-periods.show', period.id)}
                                className="inline-flex items-center rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-xs font-semibold text-slate-700 hover:bg-slate-50 transition"
                            >
                                Batal
                            </Link>
                            <PrimaryButton disabled={processing} className="rounded-xl">
                                {processing ? 'Menyimpan...' : 'Simpan Perubahan'}
                            </PrimaryButton>
                        </div>
                    </form>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
