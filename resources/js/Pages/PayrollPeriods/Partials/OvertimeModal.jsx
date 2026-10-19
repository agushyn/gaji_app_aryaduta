import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import Modal from '@/Components/Modal';
import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';
import TextInput from '@/Components/TextInput';
import { useForm } from '@inertiajs/react';
import {
    AlertCircle,
    Calendar,
    Clock,
    Coffee,
    Info,
    Moon,
    Sparkles,
    Sun,
    User,
} from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';

export default function OvertimeModal({
    show,
    onClose,
    period,
    activeEmployees = [],
    overtimeConfig = {},
    entryToEdit = null,
}) {
    const isEdit = Boolean(entryToEdit);

    const defaultDayType = (dateStr) => {
        if (!dateStr) return 'working_day';
        const d = new Date(dateStr);
        const day = d.getDay();
        return (day === 0 || day === 6) ? 'holiday' : 'working_day';
    };

    const initialDate = () => {
        if (entryToEdit?.date) {
            return typeof entryToEdit.date === 'string'
                ? entryToEdit.date.slice(0, 10)
                : new Date(entryToEdit.date).toISOString().slice(0, 10);
        }
        return period?.start_date ? new Date(period.start_date).toISOString().slice(0, 10) : '';
    };

    const { data, setData, post, put, processing, errors, reset, clearErrors } = useForm({
        employee_id: entryToEdit?.employee_id || '',
        date: initialDate(),
        day_type: entryToEdit?.day_type || defaultDayType(initialDate()),
        start_time: entryToEdit?.start_time ? entryToEdit.start_time.slice(0, 5) : '17:00',
        end_time: entryToEdit?.end_time ? entryToEdit.end_time.slice(0, 5) : '20:00',
        note: entryToEdit?.note || '',
    });

    const [employeeSearch, setEmployeeSearch] = useState('');

    useEffect(() => {
        if (show) {
            clearErrors();
            if (entryToEdit) {
                const dateStr = typeof entryToEdit.date === 'string'
                    ? entryToEdit.date.slice(0, 10)
                    : new Date(entryToEdit.date).toISOString().slice(0, 10);
                setData({
                    employee_id: entryToEdit.employee_id,
                    date: dateStr,
                    day_type: entryToEdit.day_type,
                    start_time: entryToEdit.start_time ? entryToEdit.start_time.slice(0, 5) : '17:00',
                    end_time: entryToEdit.end_time ? entryToEdit.end_time.slice(0, 5) : '20:00',
                    note: entryToEdit.note || '',
                });
            } else {
                const newDate = initialDate();
                setData({
                    employee_id: '',
                    date: newDate,
                    day_type: defaultDayType(newDate),
                    start_time: '17:00',
                    end_time: '20:00',
                    note: '',
                });
            }
            setEmployeeSearch('');
        }
    }, [show, entryToEdit]);

    // Handle date change with auto-suggestion for day_type
    const handleDateChange = (newDate) => {
        setData((prev) => ({
            ...prev,
            date: newDate,
            day_type: prev.day_type || defaultDayType(newDate),
        }));
    };

    // Selected Employee Object
    const selectedEmployee = useMemo(() => {
        return activeEmployees.find((e) => String(e.id) === String(data.employee_id)) || null;
    }, [activeEmployees, data.employee_id]);

    // Filter employees for searchable select
    const filteredEmployees = useMemo(() => {
        if (!employeeSearch.trim()) return activeEmployees;
        const q = employeeSearch.toLowerCase();
        return activeEmployees.filter(
            (e) =>
                e.full_name?.toLowerCase().includes(q) ||
                e.employee_number?.toLowerCase().includes(q) ||
                e.department?.toLowerCase().includes(q)
        );
    }, [activeEmployees, employeeSearch]);

    // Live calculation of duration from start_time and end_time (handling cross-midnight)
    const durationCalculation = useMemo(() => {
        const start = data.start_time?.trim();
        const end = data.end_time?.trim();
        if (!start || !end) return null;

        const startParts = start.split(':').map(Number);
        const endParts = end.split(':').map(Number);

        if (
            startParts.length < 2 ||
            endParts.length < 2 ||
            isNaN(startParts[0]) ||
            isNaN(startParts[1]) ||
            isNaN(endParts[0]) ||
            isNaN(endParts[1])
        ) {
            return null;
        }

        const startH = startParts[0];
        const startM = startParts[1];
        const endH = endParts[0];
        const endM = endParts[1];

        const startMinutes = startH * 60 + startM;
        const endMinutes = endH * 60 + endM;

        if (startMinutes === endMinutes) {
            return {
                isZero: true,
                minutes: 0,
                hours: 0,
                formatted: '0 jam (Jam mulai dan selesai sama)',
                crossesMidnight: false,
            };
        }

        let minutes = 0;
        let crossesMidnight = false;

        if (endMinutes < startMinutes) {
            minutes = endMinutes + 1440 - startMinutes;
            crossesMidnight = true;
        } else {
            minutes = endMinutes - startMinutes;
            crossesMidnight = false;
        }

        const wholeHours = Math.floor(minutes / 60);
        const remMinutes = minutes % 60;
        const decimalHours = Math.round((minutes / 60) * 100) / 100;

        const formatted =
            remMinutes > 0
                ? `${wholeHours} jam ${remMinutes} menit (${decimalHours.toFixed(2)} jam)`
                : `${wholeHours} jam (${decimalHours.toFixed(2)} jam)`;

        // Break threshold evaluation
        const isHoliday = data.day_type === 'holiday';
        const threshold = isHoliday
            ? Number(overtimeConfig.holiday_threshold ?? 4.0)
            : Number(overtimeConfig.working_day_threshold ?? 3.0);
        const durationPerDay = isHoliday
            ? Number(overtimeConfig.holiday_duration ?? 1.0)
            : Number(overtimeConfig.working_day_duration ?? 0.5);

        const thresholdMinutes = Math.round(threshold * 60);
        const thresholdReached = minutes >= thresholdMinutes;
        const deductionHours = thresholdReached ? durationPerDay : 0;
        const netHours = Math.max(0, decimalHours - deductionHours);

        // Estimated wage calculation
        let estimatedPay = 0;
        const rate = Number(selectedEmployee?.default_overtime_rate || 0);

        if (rate > 0 && netHours > 0) {
            if (isHoliday) {
                estimatedPay = netHours * rate * 2.0;
            } else {
                const firstHour = Math.min(netHours, 1.0);
                const subHours = Math.max(0, netHours - 1.0);
                estimatedPay = firstHour * rate * 1.5 + subHours * rate * 2.0;
            }
        }

        return {
            isZero: false,
            minutes,
            hours: decimalHours,
            formatted,
            crossesMidnight,
            threshold,
            thresholdMinutes,
            durationPerDay,
            thresholdReached,
            deductionHours,
            netHours,
            estimatedPay,
            rate,
        };
    }, [data.start_time, data.end_time, data.day_type, overtimeConfig, selectedEmployee]);

    const handleSubmit = (e) => {
        e.preventDefault();
        if (isEdit) {
            put(route('payroll-periods.overtimes.update', [period.id, entryToEdit.id]), {
                preserveScroll: true,
                onSuccess: () => {
                    reset();
                    onClose();
                },
            });
        } else {
            post(route('payroll-periods.overtimes.store', period.id), {
                preserveScroll: true,
                onSuccess: () => {
                    reset();
                    onClose();
                },
            });
        }
    };

    const formatRupiah = (val) => {
        return 'Rp ' + Number(val || 0).toLocaleString('id-ID');
    };

    return (
        <Modal show={show} onClose={onClose} maxWidth="xl">
            <form onSubmit={handleSubmit} className="p-6">
                <div className="flex items-center justify-between border-b border-slate-100 pb-4">
                    <div className="flex items-center gap-2.5">
                        <div className="flex h-10 w-10 items-center justify-center rounded-xl bg-blue-50 text-[#0B57D0]">
                            <Clock className="h-5 w-5" />
                        </div>
                        <div>
                            <h2 className="text-base font-bold text-slate-900">
                                {isEdit ? 'Ubah Data Lembur Karyawan' : 'Tambah Data Lembur Karyawan'}
                            </h2>
                            <p className="text-xs text-slate-500">
                                Periode: <strong className="font-semibold text-slate-700">{period?.period_code}</strong>
                                {' '}({period?.start_date ? new Date(period.start_date).toLocaleDateString('id-ID') : ''} s/d {period?.end_date ? new Date(period.end_date).toLocaleDateString('id-ID') : ''})
                            </p>
                        </div>
                    </div>
                </div>

                <div className="mt-5 space-y-4">
                    {/* Employee Picker */}
                    <div>
                        <InputLabel htmlFor="employee_id" value="Karyawan *" />
                        {activeEmployees.length > 8 && (
                            <div className="mb-1.5 mt-1">
                                <input
                                    type="text"
                                    value={employeeSearch}
                                    onChange={(e) => setEmployeeSearch(e.target.value)}
                                    placeholder="Ketik nama atau NIK untuk memfilter..."
                                    className="w-full rounded-lg border border-slate-200 bg-slate-50/60 px-3 py-1.5 text-xs focus:border-indigo-600 focus:bg-white focus:ring-1 focus:ring-indigo-600"
                                />
                            </div>
                        )}
                        <select
                            id="employee_id"
                            value={data.employee_id}
                            onChange={(e) => setData('employee_id', e.target.value)}
                            className="mt-1 block w-full rounded-xl border-slate-300 py-2.5 px-3 text-xs focus:border-indigo-600 focus:ring-1 focus:ring-indigo-600"
                            required
                        >
                            <option value="">-- Pilih Karyawan --</option>
                            {filteredEmployees.map((emp) => (
                                <option key={emp.id} value={emp.id}>
                                    {emp.full_name} ({emp.employee_number}) - {emp.department || 'Umum'} {emp.default_overtime_rate ? `• Tarif: ${formatRupiah(emp.default_overtime_rate)}/jam` : ''}
                                </option>
                            ))}
                        </select>
                        <InputError message={errors.employee_id} className="mt-1" />

                        {selectedEmployee && (
                            <div className="mt-2 flex items-center justify-between rounded-lg bg-slate-50 px-3 py-2 text-[11px] text-slate-600">
                                <div className="flex items-center gap-1.5">
                                    <User className="h-3.5 w-3.5 text-slate-400" />
                                    <span>{selectedEmployee.full_name}</span>
                                    <span className="text-slate-400">({selectedEmployee.position || selectedEmployee.department || 'Staff'})</span>
                                </div>
                                <div className="font-semibold text-slate-800">
                                    Tarif Lembur: {selectedEmployee.default_overtime_rate ? formatRupiah(selectedEmployee.default_overtime_rate) + '/jam' : 'Belum diatur'}
                                </div>
                            </div>
                        )}
                    </div>

                    {/* Date and Day Type Row */}
                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <InputLabel htmlFor="date" value="Tanggal Lembur *" />
                            <div className="relative mt-1">
                                <input
                                    type="date"
                                    id="date"
                                    value={data.date}
                                    min={period?.start_date ? new Date(period.start_date).toISOString().slice(0, 10) : undefined}
                                    max={period?.end_date ? new Date(period.end_date).toISOString().slice(0, 10) : undefined}
                                    onChange={(e) => handleDateChange(e.target.value)}
                                    className="block w-full rounded-xl border border-slate-300 py-2.5 px-3 text-xs focus:border-indigo-600 focus:ring-1 focus:ring-indigo-600"
                                    required
                                />
                            </div>
                            <InputError message={errors.date} className="mt-1" />
                        </div>

                        <div>
                            <InputLabel value="Jenis Hari *" />
                            <div className="mt-1 grid grid-cols-2 gap-2">
                                <button
                                    type="button"
                                    onClick={() => setData('day_type', 'working_day')}
                                    className={`inline-flex items-center justify-center gap-1.5 rounded-xl border px-3 py-2 text-xs font-semibold transition ${
                                        data.day_type === 'working_day'
                                            ? 'border-indigo-600 bg-indigo-50/70 text-indigo-700 shadow-xs'
                                            : 'border-slate-200 bg-white text-slate-600 hover:bg-slate-50'
                                    }`}
                                >
                                    <Sun className="h-3.5 w-3.5 text-amber-500" />
                                    <span>Hari Biasa</span>
                                </button>

                                <button
                                    type="button"
                                    onClick={() => setData('day_type', 'holiday')}
                                    className={`inline-flex items-center justify-center gap-1.5 rounded-xl border px-3 py-2 text-xs font-semibold transition ${
                                        data.day_type === 'holiday'
                                            ? 'border-rose-600 bg-rose-50/70 text-rose-700 shadow-xs'
                                            : 'border-slate-200 bg-white text-slate-600 hover:bg-slate-50'
                                    }`}
                                >
                                    <Coffee className="h-3.5 w-3.5 text-rose-500" />
                                    <span>Hari Libur</span>
                                </button>
                            </div>
                            <InputError message={errors.day_type} className="mt-1" />
                        </div>
                    </div>

                    {/* Start Time and End Time */}
                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <InputLabel htmlFor="start_time" value="Jam Mulai *" />
                            <input
                                type="time"
                                id="start_time"
                                value={data.start_time}
                                onChange={(e) => setData('start_time', e.target.value)}
                                className="mt-1 block w-full rounded-xl border border-slate-300 py-2.5 px-3 text-xs focus:border-indigo-600 focus:ring-1 focus:ring-indigo-600 font-mono"
                                required
                            />
                            <InputError message={errors.start_time} className="mt-1" />
                        </div>

                        <div>
                            <InputLabel htmlFor="end_time" value="Jam Selesai *" />
                            <input
                                type="time"
                                id="end_time"
                                value={data.end_time}
                                onChange={(e) => setData('end_time', e.target.value)}
                                className="mt-1 block w-full rounded-xl border border-slate-300 py-2.5 px-3 text-xs focus:border-indigo-600 focus:ring-1 focus:ring-indigo-600 font-mono"
                                required
                            />
                            <InputError message={errors.end_time} className="mt-1" />
                        </div>
                    </div>

                    {/* Auto-Calculated Duration & Breakdown Banner */}
                    {durationCalculation && (
                        <div className="rounded-xl border border-blue-100 bg-blue-50/50 p-4 space-y-2">
                            <div className="flex items-center justify-between">
                                <span className="text-xs font-semibold text-blue-900 flex items-center gap-1.5">
                                    <Sparkles className="h-4 w-4 text-[#0B57D0]" />
                                    <span>Durasi Lembur Terhitung:</span>
                                </span>
                                <div className="text-right">
                                    <span className="text-sm font-extrabold text-[#0B57D0]">
                                        {durationCalculation.formatted}
                                    </span>
                                    {durationCalculation.crossesMidnight && (
                                        <span className="ml-2 inline-flex items-center gap-1 rounded-full bg-indigo-100 px-2 py-0.5 text-[10px] font-semibold text-indigo-700">
                                            <Moon className="h-3 w-3" />
                                            <span>Lintas Tengah Malam</span>
                                        </span>
                                    )}
                                </div>
                            </div>

                            {/* Break Deduction & Formula Notice */}
                            <div className="text-[11px] text-slate-600 border-t border-blue-100 pt-2 flex flex-col gap-1">
                                <div className="flex items-center justify-between">
                                    <span>
                                        Threshold Istirahat ({data.day_type === 'holiday' ? 'Hari Libur' : 'Hari Biasa'}):
                                        <strong className="ml-1 text-slate-800">≥ {durationCalculation.threshold} jam</strong>
                                    </span>
                                    <span
                                        className={`font-semibold ${
                                            durationCalculation.thresholdReached ? 'text-amber-700' : 'text-slate-500'
                                        }`}
                                    >
                                        {durationCalculation.thresholdReached
                                            ? `Dipotong ${durationCalculation.deductionHours} jam`
                                            : 'Bebas Potongan'}
                                    </span>
                                </div>

                                <div className="flex items-center justify-between font-medium">
                                    <span>Durasi Bersih yang Dibayar:</span>
                                    <span className="font-bold text-slate-900">
                                        {durationCalculation.netHours.toFixed(2)} jam
                                    </span>
                                </div>

                                {durationCalculation.rate > 0 && (
                                    <div className="flex items-center justify-between border-t border-blue-100/70 pt-1.5 font-bold text-emerald-700">
                                        <span>Estimasi Upah Lembur:</span>
                                        <span className="text-xs">
                                            {formatRupiah(durationCalculation.estimatedPay)}
                                        </span>
                                    </div>
                                )}
                            </div>
                        </div>
                    )}

                    {/* Overtime Note */}
                    <div>
                        <InputLabel htmlFor="note" value="Keterangan Lembur (Opsional)" />
                        <textarea
                            id="note"
                            value={data.note}
                            onChange={(e) => setData('note', e.target.value)}
                            rows={2}
                            placeholder="Contoh: Pekerjaan maintenance server / closing akhir bulan..."
                            className="mt-1 block w-full rounded-xl border-slate-300 py-2 px-3 text-xs focus:border-indigo-600 focus:ring-1 focus:ring-indigo-600"
                        />
                        <InputError message={errors.note} className="mt-1" />
                    </div>
                </div>

                <div className="mt-6 flex items-center justify-end gap-3 border-t border-slate-100 pt-4">
                    <SecondaryButton type="button" onClick={onClose} disabled={processing}>
                        Batal
                    </SecondaryButton>
                    <PrimaryButton type="submit" disabled={processing || (durationCalculation?.isZero)}>
                        {processing ? 'Menyimpan...' : isEdit ? 'Simpan Perubahan' : 'Tambah Entri Lembur'}
                    </PrimaryButton>
                </div>
            </form>
        </Modal>
    );
}
