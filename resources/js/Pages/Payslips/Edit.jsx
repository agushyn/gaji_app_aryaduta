import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, useForm } from '@inertiajs/react';
import {
    AlertCircle,
    ArrowLeft,
    Edit3,
    Receipt,
    Trash2,
    TrendingDown,
    TrendingUp,
    User,
    Wallet,
} from 'lucide-react';

export default function PayslipsEdit({ payslip, payComponents }) {
    const { data, setData, put, processing, errors } = useForm({
        items: payslip.items?.map((item) => ({
            pay_component_id: item.pay_component_id || '',
            item_code_snapshot: item.item_code_snapshot,
            description_snapshot: item.description_snapshot,
            item_type: item.item_type,
            amount: parseFloat(item.amount) || 0,
        })) || [],
    });

    // Live preview calculation on client-side
    const previewEarnings = data.items
        .filter((item) => item.item_type === 'earning')
        .reduce((sum, item) => sum + (parseFloat(item.amount) || 0), 0);

    const previewDeductions = data.items
        .filter((item) => item.item_type === 'deduction')
        .reduce((sum, item) => sum + (parseFloat(item.amount) || 0), 0);

    const previewNet = previewEarnings - previewDeductions;

    const formatRupiah = (val) => {
        return 'Rp ' + Number(val || 0).toLocaleString('id-ID');
    };

    const handleComponentChange = (index, componentId) => {
        const component = payComponents?.find((c) => String(c.id) === String(componentId));
        const updated = [...data.items];

        if (component) {
            updated[index] = {
                ...updated[index],
                pay_component_id: component.id,
                item_code_snapshot: component.code,
                description_snapshot: component.name,
                item_type: component.type,
            };
        } else {
            updated[index] = {
                ...updated[index],
                pay_component_id: '',
            };
        }

        setData('items', updated);
    };

    const handleItemFieldChange = (index, field, value) => {
        const updated = [...data.items];
        updated[index] = {
            ...updated[index],
            [field]: value,
        };
        setData('items', updated);
    };

    const addItemRow = (type = 'earning') => {
        setData('items', [
            ...data.items,
            {
                pay_component_id: '',
                item_code_snapshot: '',
                description_snapshot: '',
                item_type: type,
                amount: 0,
            },
        ]);
    };

    const removeItemRow = (index) => {
        if (data.items.length <= 1) {
            alert('Rincian slip gaji harus memiliki minimal 1 baris komponen.');
            return;
        }
        const updated = data.items.filter((_, idx) => idx !== index);
        setData('items', updated);
    };

    const submit = (e) => {
        e.preventDefault();
        put(route('payslips.update', payslip.id));
    };

    return (
        <AuthenticatedLayout header={`Ubah Slip: ${payslip.slip_number}`}>
            <Head title={`Edit Slip - ${payslip.slip_number}`} />

            <div className="max-w-5xl mx-auto space-y-6">
                {/* Navigation Back */}
                <div className="flex items-center gap-3">
                    <Link
                        href={route('payslips.show', payslip.id)}
                        className="inline-flex items-center gap-1.5 text-xs font-semibold text-slate-500 hover:text-slate-900 transition"
                    >
                        <ArrowLeft className="h-4 w-4" />
                        <span>Kembali ke Detail Slip Gaji</span>
                    </Link>
                </div>

                {/* Edit Form Card */}
                <div className="rounded-2xl border border-slate-200 bg-white p-6 sm:p-8 shadow-xs">
                    <div className="border-b border-slate-100 pb-5">
                        <div className="flex items-center gap-2 text-indigo-600 font-semibold text-xs uppercase tracking-wider">
                            <Edit3 className="h-4 w-4" />
                            <span>Edit Komponen Slip Gaji</span>
                        </div>
                        <h1 className="mt-1 text-xl font-bold text-slate-900">
                            Perbarui Rincian Gaji: {payslip.employee_name_snapshot}
                        </h1>
                        <p className="mt-1 text-xs text-slate-500">
                            Nomor Slip: <span className="font-mono font-bold text-indigo-600">{payslip.slip_number}</span> | Periode:{' '}
                            <span className="font-semibold text-slate-700">{payslip.payroll_period?.period_code}</span>
                        </p>
                    </div>

                    {(payslip.status === 'pending_review' || payslip.status === 'approved') && (
                        <div className="mt-4 p-4 rounded-xl bg-amber-50 border border-amber-200 text-amber-900 flex items-start gap-3">
                            <AlertCircle className="h-5 w-5 text-amber-600 shrink-0 mt-0.5" />
                            <div className="text-xs space-y-1">
                                <p className="font-semibold text-amber-950">
                                    Peringatan Pembatalan Persetujuan
                                </p>
                                <p className="text-amber-800">
                                    Slip gaji ini saat ini berstatus <strong>{payslip.status_label || payslip.status}</strong>. Menyimpan perubahan komponen atau nominal akan otomatis membatalkan status persetujuan sebelumnya dan mengembalikan slip (serta periode terkait) ke status Draft untuk diajukan ulang.
                                </p>
                            </div>
                        </div>
                    )}

                    <form onSubmit={submit} className="mt-6 space-y-6">
                        {/* Dynamic Items Table */}
                        <div className="space-y-3">
                            <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2 border-b border-slate-100 pb-3">
                                <div>
                                    <h2 className="text-sm font-bold text-slate-900">
                                        Rincian Pos Pendapatan & Potongan
                                    </h2>
                                    <p className="text-[11px] text-slate-500">
                                        Perubahan komponen akan dihitung ulang secara transaksional di backend.
                                    </p>
                                </div>

                                <div className="flex gap-2">
                                    <button
                                        type="button"
                                        onClick={() => addItemRow('earning')}
                                        className="inline-flex items-center gap-1.5 rounded-lg border border-emerald-300 bg-emerald-50 px-3 py-1.5 text-xs font-semibold text-emerald-800 hover:bg-emerald-100 transition"
                                    >
                                        <TrendingUp className="h-3.5 w-3.5" />
                                        <span>+ Pendapatan</span>
                                    </button>

                                    <button
                                        type="button"
                                        onClick={() => addItemRow('deduction')}
                                        className="inline-flex items-center gap-1.5 rounded-lg border border-rose-300 bg-rose-50 px-3 py-1.5 text-xs font-semibold text-rose-800 hover:bg-rose-100 transition"
                                    >
                                        <TrendingDown className="h-3.5 w-3.5" />
                                        <span>+ Potongan</span>
                                    </button>
                                </div>
                            </div>

                            <InputError message={errors.items} className="mt-1" />

                            <div className="overflow-x-auto rounded-xl border border-slate-200">
                                <table className="min-w-full divide-y divide-slate-200 text-left text-xs">
                                    <thead className="bg-slate-50 text-slate-700 font-semibold uppercase tracking-wider">
                                        <tr>
                                            <th className="px-3 py-3 w-12 text-center">#</th>
                                            <th className="px-3 py-3 w-56">Master Komponen</th>
                                            <th className="px-3 py-3">Deskripsi di Slip</th>
                                            <th className="px-3 py-3 w-36">Tipe Pos</th>
                                            <th className="px-3 py-3 w-48 text-right">Nominal (Rp) *</th>
                                            <th className="px-3 py-3 w-16 text-center">Hapus</th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-slate-100 bg-white">
                                        {data.items.map((item, idx) => (
                                            <tr
                                                key={idx}
                                                className={item.item_type === 'earning' ? 'hover:bg-emerald-50/20' : 'hover:bg-rose-50/20'}
                                            >
                                                <td className="px-3 py-3 text-center text-slate-400 font-mono">
                                                    {idx + 1}
                                                </td>

                                                {/* Component Selector */}
                                                <td className="px-3 py-3">
                                                    <select
                                                        value={item.pay_component_id || ''}
                                                        onChange={(e) => handleComponentChange(idx, e.target.value)}
                                                        className="w-full rounded-md border-slate-300 text-xs shadow-xs focus:border-indigo-600 focus:ring-indigo-600"
                                                    >
                                                        <option value="">Komponen Kustom</option>
                                                        {payComponents?.map((comp) => (
                                                            <option key={comp.id} value={comp.id}>
                                                                {comp.name} ({comp.type === 'earning' ? '+' : '-'})
                                                            </option>
                                                        ))}
                                                    </select>
                                                </td>

                                                {/* Description Snapshot */}
                                                <td className="px-3 py-3">
                                                    <input
                                                        type="text"
                                                        value={item.description_snapshot}
                                                        onChange={(e) =>
                                                            handleItemFieldChange(idx, 'description_snapshot', e.target.value)
                                                        }
                                                        placeholder="Keterangan komponen..."
                                                        className="w-full rounded-md border-slate-300 text-xs shadow-xs focus:border-indigo-600 focus:ring-indigo-600"
                                                        required
                                                    />
                                                </td>

                                                {/* Item Type */}
                                                <td className="px-3 py-3">
                                                    <select
                                                        value={item.item_type}
                                                        onChange={(e) =>
                                                            handleItemFieldChange(idx, 'item_type', e.target.value)
                                                        }
                                                        className={`w-full rounded-md text-xs font-semibold shadow-xs border-slate-300 focus:ring-indigo-600 ${
                                                            item.item_type === 'earning'
                                                                ? 'text-emerald-800 bg-emerald-50/50'
                                                                : 'text-rose-800 bg-rose-50/50'
                                                        }`}
                                                    >
                                                        <option value="earning">Pendapatan (+)</option>
                                                        <option value="deduction">Potongan (-)</option>
                                                    </select>
                                                </td>

                                                {/* Amount */}
                                                <td className="px-3 py-3 text-right">
                                                    <input
                                                        type="number"
                                                        step="1"
                                                        min="0"
                                                        value={item.amount}
                                                        onChange={(e) =>
                                                            handleItemFieldChange(idx, 'amount', e.target.value)
                                                        }
                                                        className={`w-full text-right rounded-md text-xs font-mono font-bold shadow-xs border-slate-300 focus:border-indigo-600 focus:ring-indigo-600 ${
                                                            item.item_type === 'earning'
                                                                ? 'text-emerald-700'
                                                                : 'text-rose-700'
                                                        }`}
                                                        placeholder="0"
                                                        required
                                                    />
                                                    {errors[`items.${idx}.amount`] && (
                                                        <p className="mt-1 text-[11px] text-rose-600 text-right">
                                                            {errors[`items.${idx}.amount`]}
                                                        </p>
                                                    )}
                                                </td>

                                                {/* Delete Row */}
                                                <td className="px-3 py-3 text-center">
                                                    <button
                                                        type="button"
                                                        onClick={() => removeItemRow(idx)}
                                                        className="text-slate-400 hover:text-rose-600 transition p-1"
                                                        title="Hapus baris ini"
                                                    >
                                                        <Trash2 className="h-4 w-4" />
                                                    </button>
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        {/* Live Preview Calculation Card */}
                        <div className="rounded-xl border border-slate-200 bg-slate-50 p-5 space-y-3">
                            <div className="flex items-center justify-between text-xs text-slate-500 border-b border-slate-200 pb-2">
                                <span className="font-semibold text-slate-700 uppercase tracking-wider">
                                    Pratinjau Hasil Perhitungan
                                </span>
                                <span className="text-[11px] italic">
                                    Gaji Pokok tidak terhitung ganda
                                </span>
                            </div>

                            <div className="grid grid-cols-1 sm:grid-cols-3 gap-4 text-xs">
                                <div className="rounded-lg bg-white p-3 border border-slate-200 shadow-xs">
                                    <span className="text-slate-500 block">Total Pendapatan (Gross)</span>
                                    <span className="text-lg font-bold text-emerald-700 mt-1 block">
                                        {formatRupiah(previewEarnings)}
                                    </span>
                                </div>

                                <div className="rounded-lg bg-white p-3 border border-slate-200 shadow-xs">
                                    <span className="text-slate-500 block">Total Potongan</span>
                                    <span className="text-lg font-bold text-rose-700 mt-1 block">
                                        {formatRupiah(previewDeductions)}
                                    </span>
                                </div>

                                <div className="rounded-lg bg-indigo-50/80 p-3 border border-indigo-200 shadow-xs">
                                    <span className="text-indigo-900 font-bold block uppercase tracking-wider">
                                        Gaji Bersih (Take Home Pay)
                                    </span>
                                    <span className="text-lg font-extrabold text-indigo-700 mt-1 block">
                                        {formatRupiah(previewNet)}
                                    </span>
                                </div>
                            </div>
                        </div>

                        {/* Submit Actions */}
                        <div className="mt-8 flex items-center justify-end gap-3 border-t border-slate-100 pt-5">
                            <Link
                                href={route('payslips.show', payslip.id)}
                                className="inline-flex items-center rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-xs font-semibold text-slate-700 hover:bg-slate-50 transition"
                            >
                                Batal
                            </Link>
                            <PrimaryButton disabled={processing} className="rounded-xl">
                                {processing ? 'Menyimpan Perubahan...' : 'Simpan Perubahan Slip'}
                            </PrimaryButton>
                        </div>
                    </form>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
