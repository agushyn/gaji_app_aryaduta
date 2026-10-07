import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';
import TextInput from '@/Components/TextInput';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, useForm, usePage } from '@inertiajs/react';
import {
    AlertCircle,
    ArrowLeft,
    Check,
    Coins,
    FileSpreadsheet,
    Info,
    Plus,
    Receipt,
    Trash2,
    TrendingDown,
    TrendingUp,
    User,
    Wallet,
} from 'lucide-react';
import { useEffect, useState } from 'react';

export default function PayslipsCreate({
    periods,
    selectedPeriod,
    employees,
    existingEmployeeIds = [],
    payComponents,
}) {
    const defaultPeriodId = selectedPeriod ? selectedPeriod.id : (periods[0]?.id || '');

    // Prepare default items from active pay components (e.g. Gaji Pokok, Tunjangan, BPJS)
    const initialItems = () => {
        if (!payComponents || payComponents.length === 0) {
            return [
                {
                    pay_component_id: '',
                    item_code_snapshot: 'BASIC_SALARY',
                    description_snapshot: 'Gaji Pokok',
                    item_type: 'earning',
                    amount: 5000000,
                },
            ];
        }

        return payComponents.slice(0, 4).map((comp) => ({
            pay_component_id: comp.id,
            item_code_snapshot: comp.code,
            description_snapshot: comp.name,
            item_type: comp.type,
            amount: comp.code === 'BASIC_SALARY' ? 5000000 : 0,
        }));
    };

    const { data, setData, post, processing, errors } = useForm({
        payroll_period_id: defaultPeriodId,
        employee_id: '',
        items: initialItems(),
    });

    const [selectedEmployee, setSelectedEmployee] = useState(null);

    // Update selected employee details and pre-populate master salary items when employee_id changes
    useEffect(() => {
        const emp = employees?.find((e) => String(e.id) === String(data.employee_id));
        setSelectedEmployee(emp || null);

        if (emp) {
            const period = periods?.find((p) => String(p.id) === String(data.payroll_period_id));
            const workingDays = period?.working_days || selectedPeriod?.working_days || 25;

            const newItems = [];
            // Gaji Pokok
            if (emp.gaji_pokok !== undefined && emp.gaji_pokok !== null) {
                newItems.push({
                    pay_component_id: payComponents?.find((c) => c.code === 'BASIC_SALARY')?.id || '',
                    item_code_snapshot: 'BASIC_SALARY',
                    description_snapshot: 'Gaji Pokok',
                    item_type: 'earning',
                    amount: emp.gaji_pokok || 0,
                    note: 'Gaji Pokok master',
                });
            }
            // Tunjangan Jabatan
            if (parseFloat(emp.tunjangan_jabatan) > 0) {
                newItems.push({
                    pay_component_id: payComponents?.find((c) => c.code === 'POSITION_ALLOWANCE')?.id || '',
                    item_code_snapshot: 'POSITION_ALLOWANCE',
                    description_snapshot: 'Tunjangan Jabatan',
                    item_type: 'earning',
                    amount: emp.tunjangan_jabatan,
                    note: 'Tunjangan Jabatan master',
                });
            }
            // Tunjangan Kehadiran
            if (parseFloat(emp.tunjangan_kehadiran) > 0) {
                newItems.push({
                    pay_component_id: payComponents?.find((c) => c.code === 'ATTENDANCE_INCENTIVE')?.id || '',
                    item_code_snapshot: 'ATTENDANCE_INCENTIVE',
                    description_snapshot: 'Tunjangan Kehadiran',
                    item_type: 'earning',
                    amount: emp.tunjangan_kehadiran,
                    note: 'Tunjangan Kehadiran master',
                });
            }
            // Tunjangan Keluarga
            if (parseFloat(emp.tunjangan_keluarga) > 0) {
                newItems.push({
                    pay_component_id: payComponents?.find((c) => c.code === 'FAMILY_ALLOWANCE')?.id || '',
                    item_code_snapshot: 'FAMILY_ALLOWANCE',
                    description_snapshot: 'Tunjangan Keluarga',
                    item_type: 'earning',
                    amount: emp.tunjangan_keluarga,
                    note: 'Tunjangan Keluarga master',
                });
            }
            // Uang Makan
            if (parseFloat(emp.uang_makan_per_hari) > 0) {
                newItems.push({
                    pay_component_id: payComponents?.find((c) => c.code === 'MEAL_ALLOWANCE')?.id || '',
                    item_code_snapshot: 'MEAL_ALLOWANCE',
                    description_snapshot: 'Uang Makan',
                    item_type: 'earning',
                    quantity: workingDays,
                    unit: 'hari',
                    rate: emp.uang_makan_per_hari,
                    amount: parseFloat(emp.uang_makan_per_hari) * workingDays,
                    note: `Uang Makan (${workingDays} hari kerja)`,
                });
            }
            // Uang Transportasi
            if (parseFloat(emp.uang_transportasi_per_hari) > 0) {
                newItems.push({
                    pay_component_id: payComponents?.find((c) => c.code === 'TRANSPORT_ALLOWANCE')?.id || '',
                    item_code_snapshot: 'TRANSPORT_ALLOWANCE',
                    description_snapshot: 'Uang Transport',
                    item_type: 'earning',
                    quantity: workingDays,
                    unit: 'hari',
                    rate: emp.uang_transportasi_per_hari,
                    amount: parseFloat(emp.uang_transportasi_per_hari) * workingDays,
                    note: `Uang Transport (${workingDays} hari kerja)`,
                });
            }
            // BPJS Ketenagakerjaan
            if (emp.bpjs_ketenagakerjaan_enabled && parseFloat(emp.bpjs_ketenagakerjaan_amount) > 0) {
                newItems.push({
                    pay_component_id: payComponents?.find((c) => c.code === 'BPJS_TK')?.id || '',
                    item_code_snapshot: 'BPJS_TK',
                    description_snapshot: 'BPJS Ketenagakerjaan',
                    item_type: 'deduction',
                    amount: emp.bpjs_ketenagakerjaan_amount,
                    note: 'Potongan BPJS Ketenagakerjaan master',
                });
            }

            if (newItems.length > 0) {
                setData((prev) => ({
                    ...prev,
                    items: newItems,
                }));
            }
        }
    }, [data.employee_id, employees]);

    // Live preview calculation on client-side (backend always recalculates strictly)
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

    // Item handlers
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
        post(route('payslips.store'));
    };

    return (
        <AuthenticatedLayout header="Input Slip Gaji Karyawan">
            <Head title="Input Slip Gaji" />

            <div className="max-w-5xl mx-auto space-y-6">
                {/* Navigation Back */}
                <div className="flex items-center gap-3">
                    <Link
                        href={
                            data.payroll_period_id
                                ? route('payroll-periods.show', data.payroll_period_id)
                                : route('payroll-periods.index')
                        }
                        className="inline-flex items-center gap-1.5 text-xs font-semibold text-slate-500 hover:text-slate-900 transition"
                    >
                        <ArrowLeft className="h-4 w-4" />
                        <span>Kembali ke Periode Gaji</span>
                    </Link>
                </div>

                {/* Main Form Card */}
                <div className="rounded-2xl border border-slate-200 bg-white p-6 sm:p-8 shadow-xs">
                    <div className="border-b border-slate-100 pb-5">
                        <div className="flex items-center gap-2 text-indigo-600 font-semibold text-xs uppercase tracking-wider">
                            <Receipt className="h-4 w-4" />
                            <span>Formulir Penginputan Slip</span>
                        </div>
                        <h1 className="mt-1 text-xl font-bold text-slate-900">
                            Input Rincian Slip Gaji Karyawan
                        </h1>
                        <p className="mt-1 text-xs text-slate-500">
                            Pilih periode penggajian dan karyawan penerima. Masukkan nominal setiap pos komponen gaji dinamis.
                        </p>
                    </div>

                    <form onSubmit={submit} className="mt-6 space-y-6">
                        {/* Section 1: Periode & Karyawan Header */}
                        <div className="rounded-xl border border-slate-100 bg-slate-50/60 p-4 sm:p-5 space-y-4">
                            <h2 className="text-xs font-bold uppercase tracking-wider text-slate-700 flex items-center gap-1.5">
                                <User className="h-4 w-4 text-indigo-600" />
                                1. Target Periode & Karyawan Penerima
                            </h2>

                            <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div>
                                    <InputLabel htmlFor="payroll_period_id" value="Periode Penggajian *" />
                                    <select
                                        id="payroll_period_id"
                                        value={data.payroll_period_id}
                                        onChange={(e) => setData('payroll_period_id', e.target.value)}
                                        className="mt-1 block w-full rounded-md border-slate-300 shadow-xs focus:border-indigo-600 focus:ring-indigo-600 text-xs font-medium"
                                        required
                                    >
                                        <option value="">Pilih Periode Gaji</option>
                                        {periods?.map((p) => (
                                            <option key={p.id} value={p.id}>
                                                Periode {p.period_code} ({p.start_date} s/d {p.end_date})
                                            </option>
                                        ))}
                                    </select>
                                    <InputError message={errors.payroll_period_id} className="mt-1" />
                                </div>

                                <div>
                                    <InputLabel htmlFor="employee_id" value="Karyawan Penerima Gaji *" />
                                    <select
                                        id="employee_id"
                                        value={data.employee_id}
                                        onChange={(e) => setData('employee_id', e.target.value)}
                                        className="mt-1 block w-full rounded-md border-slate-300 shadow-xs focus:border-indigo-600 focus:ring-indigo-600 text-xs font-medium"
                                        required
                                    >
                                        <option value="">Pilih Karyawan Aktif</option>
                                        {employees?.map((emp) => {
                                            const hasSlip = existingEmployeeIds.includes(emp.id);
                                            return (
                                                <option
                                                    key={emp.id}
                                                    value={emp.id}
                                                    disabled={hasSlip}
                                                >
                                                    {emp.full_name} ({emp.employee_number}) - {emp.department || 'Umum'}{' '}
                                                    {hasSlip ? '(Sudah Ada Slip)' : ''}
                                                </option>
                                            );
                                        })}
                                    </select>
                                    <InputError message={errors.employee_id} className="mt-1" />
                                </div>
                            </div>

                            {/* Selected Employee Snapshot Preview */}
                            {selectedEmployee && (
                                <div className="mt-3 rounded-lg border border-indigo-100 bg-indigo-50/50 p-3 text-xs text-indigo-900 flex flex-wrap gap-4">
                                    <div>
                                        <span className="text-slate-500">NIK:</span>{' '}
                                        <span className="font-mono font-bold">{selectedEmployee.employee_number}</span>
                                    </div>
                                    <div>
                                        <span className="text-slate-500">Jabatan:</span>{' '}
                                        <span className="font-semibold">{selectedEmployee.position?.name ?? (typeof selectedEmployee.position === 'string' ? selectedEmployee.position : '-')}</span>
                                    </div>
                                    <div>
                                        <span className="text-slate-500">Departemen:</span>{' '}
                                        <span className="font-semibold">{selectedEmployee.department?.name ?? (typeof selectedEmployee.department === 'string' ? selectedEmployee.department : 'Umum')}</span>
                                    </div>
                                </div>
                            )}
                        </div>

                        {/* Section 2: Dynamic Items Table */}
                        <div className="space-y-3 pt-2">
                            <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2 border-b border-slate-100 pb-3">
                                <div>
                                    <h2 className="text-sm font-bold text-slate-900">
                                        2. Rincian Pos Pendapatan & Potongan
                                    </h2>
                                    <p className="text-[11px] text-slate-500">
                                        Tambahkan komponen pendapatan atau potongan secara dinamis. Total akhir dihitung oleh backend.
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

                        {/* Section 3: Live Preview Calculation Card */}
                        <div className="rounded-xl border border-slate-200 bg-slate-50 p-5 space-y-3">
                            <div className="flex items-center justify-between text-xs text-slate-500 border-b border-slate-200 pb-2">
                                <span className="font-semibold text-slate-700 uppercase tracking-wider">
                                    Pratinjau Hasil Perhitungan (Kalkulasi Backend Decimal)
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
                                href={
                                    data.payroll_period_id
                                        ? route('payroll-periods.show', data.payroll_period_id)
                                        : route('payroll-periods.index')
                                }
                                className="inline-flex items-center rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-xs font-semibold text-slate-700 hover:bg-slate-50 transition"
                            >
                                Batal
                            </Link>
                            <PrimaryButton disabled={processing} className="rounded-xl">
                                {processing ? 'Menyimpan Slip...' : 'Simpan Slip Gaji Draft'}
                            </PrimaryButton>
                        </div>
                    </form>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
