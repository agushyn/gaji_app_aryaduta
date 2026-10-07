import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router, usePage } from '@inertiajs/react';
import {
    AlertCircle,
    ArrowLeft,
    Check,
    CheckCircle,
    CheckSquare,
    Clock,
    FileSpreadsheet,
    Filter,
    HelpCircle,
    Info,
    Mail,
    RefreshCw,
    Search,
    Send,
    ShieldAlert,
    ShieldCheck,
    Smartphone,
    Square,
    UserCheck,
    Users,
    X,
} from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';

export default function DeliveriesBulk({
    periods = [],
    selectedPeriodId = 0,
    selectedChannel = 'both',
    preview: initialPreview = null,
    can = {},
}) {
    const { flash } = usePage().props;

    const [periodId, setPeriodId] = useState(selectedPeriodId);
    const [channel, setChannel] = useState(selectedChannel);
    const [preview, setPreview] = useState(initialPreview);
    const [loadingPreview, setLoadingPreview] = useState(false);

    // Selected payslip IDs
    const [selectedIds, setSelectedIds] = useState([]);
    const [search, setSearch] = useState('');
    const [departmentFilter, setDepartmentFilter] = useState('all');
    const [filterOnlyPending, setFilterOnlyPending] = useState(false);

    const [confirmModalOpen, setConfirmModalOpen] = useState(false);
    const [submitting, setSubmitting] = useState(false);

    // Fetch dynamic preview when period or channel changes
    const fetchPreview = (pId, ch) => {
        if (!pId || pId === 0) {
            setPreview(null);
            setSelectedIds([]);
            return;
        }

        setLoadingPreview(true);
        window.axios
            .post(route('deliveries.bulk.preview'), {
                period_id: pId,
                channel: ch,
            })
            .then((res) => {
                setPreview(res.data);
                // Pre-select all eligible recipients
                const eligibleIds = res.data.recipients
                    .filter((r) => {
                        if (ch === 'email') return r.is_email_valid && r.email_delivery_status !== 'sent' && r.email_delivery_status !== 'delivered';
                        if (ch === 'whatsapp') return r.is_phone_valid && r.whatsapp_delivery_status !== 'sent' && r.whatsapp_delivery_status !== 'delivered';
                        return (r.is_email_valid || r.is_phone_valid);
                    })
                    .map((r) => r.payslip_id);
                setSelectedIds(eligibleIds);
            })
            .catch((err) => {
                console.error('Gagal mengambil pratinjau:', err);
            })
            .finally(() => {
                setLoadingPreview(false);
            });
    };

    // On initial load, select eligible recipients if preview exists
    useEffect(() => {
        if (initialPreview && initialPreview.recipients) {
            const eligibleIds = initialPreview.recipients
                .filter((r) => {
                    if (channel === 'email') return r.is_email_valid && r.email_delivery_status !== 'sent' && r.email_delivery_status !== 'delivered';
                    if (channel === 'whatsapp') return r.is_phone_valid && r.whatsapp_delivery_status !== 'sent' && r.whatsapp_delivery_status !== 'delivered';
                    return (r.is_email_valid || r.is_phone_valid);
                })
                .map((r) => r.payslip_id);
            setSelectedIds(eligibleIds);
        }
    }, []);

    const handlePeriodChange = (newPeriodId) => {
        setPeriodId(newPeriodId);
        fetchPreview(newPeriodId, channel);
    };

    const handleChannelChange = (newChannel) => {
        setChannel(newChannel);
        fetchPreview(periodId, newChannel);
    };

    // Available departments for filter
    const departments = useMemo(() => {
        if (!preview?.recipients) return [];
        const set = new Set();
        preview.recipients.forEach((r) => {
            if (r.department) set.add(r.department);
        });
        return Array.from(set).sort();
    }, [preview]);

    // Filtered recipients
    const filteredRecipients = useMemo(() => {
        if (!preview?.recipients) return [];

        return preview.recipients.filter((r) => {
            if (departmentFilter !== 'all' && r.department !== departmentFilter) {
                return false;
            }

            if (filterOnlyPending) {
                const emailSent = r.email_delivery_status === 'sent' || r.email_delivery_status === 'delivered';
                const waSent = r.whatsapp_delivery_status === 'sent' || r.whatsapp_delivery_status === 'delivered';
                if (channel === 'email' && emailSent) return false;
                if (channel === 'whatsapp' && waSent) return false;
                if (channel === 'both' && emailSent && waSent) return false;
            }

            if (search.trim()) {
                const q = search.toLowerCase();
                const nameMatch = r.employee_name?.toLowerCase().includes(q);
                const nikMatch = r.employee_number?.toLowerCase().includes(q);
                const slipMatch = r.slip_number?.toLowerCase().includes(q);
                const emailMatch = r.email?.toLowerCase().includes(q);
                const phoneMatch = r.phone?.toLowerCase().includes(q);
                return nameMatch || nikMatch || slipMatch || emailMatch || phoneMatch;
            }

            return true;
        });
    }, [preview, departmentFilter, filterOnlyPending, search, channel]);

    const allFilteredSelected = useMemo(() => {
        if (filteredRecipients.length === 0) return false;
        return filteredRecipients.every((r) => selectedIds.includes(r.payslip_id));
    }, [filteredRecipients, selectedIds]);

    const toggleSelectAllFiltered = () => {
        if (allFilteredSelected) {
            const filteredSet = new Set(filteredRecipients.map((r) => r.payslip_id));
            setSelectedIds(selectedIds.filter((id) => !filteredSet.has(id)));
        } else {
            const newIds = new Set(selectedIds);
            filteredRecipients.forEach((r) => newIds.add(r.payslip_id));
            setSelectedIds(Array.from(newIds));
        }
    };

    const toggleSelectOne = (id) => {
        if (selectedIds.includes(id)) {
            setSelectedIds(selectedIds.filter((item) => item !== id));
        } else {
            setSelectedIds([...selectedIds, id]);
        }
    };

    const handleConfirmSubmit = () => {
        if (selectedIds.length === 0) {
            alert('Silakan pilih minimal satu penerima.');
            return;
        }

        setSubmitting(true);
        router.post(
            route('deliveries.bulk.store'),
            {
                period_id: periodId,
                channel: channel,
                payslip_ids: selectedIds,
            },
            {
                onFinish: () => {
                    setSubmitting(false);
                    setConfirmModalOpen(false);
                },
            }
        );
    };

    const formatRupiah = (val) => {
        return 'Rp ' + Number(val || 0).toLocaleString('id-ID');
    };

    return (
        <AuthenticatedLayout header="Pengiriman Massal Slip Gaji">
            <Head title="Pengiriman Massal" />

            <div className="space-y-6 max-w-7xl mx-auto">
                {/* Header Back Link & Navigation */}
                <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                    <Link
                        href={route('deliveries.index')}
                        className="inline-flex items-center gap-1.5 text-xs font-semibold text-slate-500 hover:text-slate-900 transition"
                    >
                        <ArrowLeft className="h-4 w-4" />
                        <span>Kembali ke Pelacakan Pengiriman</span>
                    </Link>

                    <div className="flex items-center gap-2">
                        <Link
                            href={route('reports.deliveries.index')}
                            className="inline-flex items-center gap-1.5 rounded-xl border border-slate-300 bg-white px-3.5 py-2 text-xs font-semibold text-slate-700 shadow-xs hover:bg-slate-50 transition"
                        >
                            <FileSpreadsheet className="h-3.5 w-3.5 text-indigo-600" />
                            <span>Laporan Pengiriman</span>
                        </Link>
                    </div>
                </div>

                {/* Configuration Card: Period & Channel */}
                <div className="rounded-2xl border border-slate-200 bg-white p-6 shadow-xs space-y-6">
                    <div>
                        <h1 className="text-base font-bold text-slate-900">
                            Konfigurasi Pengiriman Massal
                        </h1>
                        <p className="text-xs text-slate-500 mt-0.5">
                            Pilih periode gaji final, kanal pengiriman, dan tinjau status kesiapan data kontak penerima.
                        </p>
                    </div>

                    <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                        {/* Period Selector */}
                        <div>
                            <label className="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-2">
                                Periode Gaji (Hanya Final)
                            </label>
                            <select
                                value={periodId}
                                onChange={(e) => handlePeriodChange(Number(e.target.value))}
                                className="w-full rounded-xl border-slate-300 text-sm shadow-xs focus:border-indigo-500 focus:ring-indigo-500"
                            >
                                <option value={0}>Pilih Periode Gaji</option>
                                {periods.map((p) => (
                                    <option key={p.id} value={p.id}>
                                        Periode {p.period_code} ({new Date(p.start_date).toLocaleDateString('id-ID')} s/d {new Date(p.end_date).toLocaleDateString('id-ID')})
                                    </option>
                                ))}
                            </select>
                            {periods.length === 0 && (
                                <p className="text-xs text-amber-600 mt-1.5">
                                    Belum ada periode gaji yang berstatus Final. Pengiriman massal hanya dapat dilakukan untuk periode yang sudah difinalisasi.
                                </p>
                            )}
                        </div>

                        {/* Channel Selector */}
                        <div>
                            <label className="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-2">
                                Kanal Pengiriman
                            </label>
                            <div className="grid grid-cols-3 gap-2">
                                <button
                                    type="button"
                                    onClick={() => handleChannelChange('both')}
                                    className={`flex items-center justify-center gap-2 rounded-xl border px-3 py-2.5 text-xs font-semibold transition ${
                                        channel === 'both'
                                            ? 'border-[#0B57D0] bg-blue-50/70 text-blue-900 shadow-xs'
                                            : 'border-slate-200 bg-white text-slate-600 hover:bg-slate-50'
                                    }`}
                                >
                                    <span>Keduanya</span>
                                </button>

                                <button
                                    type="button"
                                    onClick={() => handleChannelChange('email')}
                                    className={`flex items-center justify-center gap-2 rounded-xl border px-3 py-2.5 text-xs font-semibold transition ${
                                        channel === 'email'
                                            ? 'border-[#0B57D0] bg-blue-50/70 text-blue-900 shadow-xs'
                                            : 'border-slate-200 bg-white text-slate-600 hover:bg-slate-50'
                                    }`}
                                >
                                    <Mail className="h-4 w-4 text-[#0B57D0]" />
                                    <span>Email</span>
                                </button>

                                <button
                                    type="button"
                                    onClick={() => handleChannelChange('whatsapp')}
                                    className={`flex items-center justify-center gap-2 rounded-xl border px-3 py-2.5 text-xs font-semibold transition ${
                                        channel === 'whatsapp'
                                            ? 'border-emerald-600 bg-emerald-50/70 text-emerald-900 shadow-xs'
                                            : 'border-slate-200 bg-white text-slate-600 hover:bg-slate-50'
                                    }`}
                                >
                                    <Smartphone className="h-4 w-4 text-emerald-600" />
                                    <span>WhatsApp</span>
                                </button>
                            </div>
                        </div>
                    </div>

                    {/* Summary Metrics Bar */}
                    {preview && (
                        <div className="grid grid-cols-2 sm:grid-cols-4 gap-3 pt-4 border-t border-slate-100">
                            <div className="rounded-xl border border-slate-200 bg-slate-50/70 p-3.5">
                                <span className="text-[11px] font-semibold uppercase tracking-wider text-slate-400 block">
                                    Total Slip Final
                                </span>
                                <span className="text-xl font-bold font-mono text-slate-800 mt-1 block">
                                    {preview.summary.total_payslips}
                                </span>
                            </div>

                            <div className="rounded-xl border border-blue-100 bg-blue-50/40 p-3.5">
                                <span className="text-[11px] font-semibold uppercase tracking-wider text-[#0B57D0] flex items-center gap-1">
                                    <Mail className="h-3 w-3" /> Siap Email
                                </span>
                                <span className="text-xl font-bold font-mono text-blue-950 mt-1 block">
                                    {preview.summary.ready_email_count}
                                    <span className="text-xs font-normal text-slate-400 ml-1.5">
                                        ({preview.summary.sent_email_count} terkirim)
                                    </span>
                                </span>
                            </div>

                            <div className="rounded-xl border border-emerald-100 bg-emerald-50/40 p-3.5">
                                <span className="text-[11px] font-semibold uppercase tracking-wider text-emerald-600 flex items-center gap-1">
                                    <Smartphone className="h-3 w-3" /> Siap WhatsApp
                                </span>
                                <span className="text-xl font-bold font-mono text-emerald-950 mt-1 block">
                                    {preview.summary.ready_whatsapp_count}
                                    <span className="text-xs font-normal text-slate-400 ml-1.5">
                                        ({preview.summary.sent_whatsapp_count} terkirim)
                                    </span>
                                </span>
                            </div>

                            <div className="rounded-xl border border-amber-100 bg-amber-50/40 p-3.5">
                                <span className="text-[11px] font-semibold uppercase tracking-wider text-amber-700 flex items-center gap-1">
                                    <AlertCircle className="h-3 w-3" /> Kontak Kurang
                                </span>
                                <div className="text-xs font-medium text-amber-900 mt-1">
                                    <span>Email: {preview.summary.missing_email_count}</span> • <span>WA: {preview.summary.missing_whatsapp_count}</span>
                                </div>
                            </div>
                        </div>
                    )}
                </div>

                {/* Recipient Selection Table Section */}
                {preview ? (
                    <div className="rounded-2xl border border-slate-200 bg-white shadow-xs overflow-hidden space-y-4">
                        {/* Table Filter and Bulk Selector Bar */}
                        <div className="p-4 border-b border-slate-100 flex flex-col md:flex-row md:items-center md:justify-between gap-3 bg-slate-50/50">
                            <div className="flex flex-wrap items-center gap-2">
                                <button
                                    type="button"
                                    onClick={toggleSelectAllFiltered}
                                    className="inline-flex items-center gap-1.5 rounded-xl border border-slate-300 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 shadow-xs hover:bg-slate-50 transition"
                                >
                                    {allFilteredSelected ? (
                                        <CheckSquare className="h-3.5 w-3.5 text-indigo-600" />
                                    ) : (
                                        <Square className="h-3.5 w-3.5 text-slate-400" />
                                    )}
                                    <span>{allFilteredSelected ? 'Batal Pilih Semua' : 'Pilih Semua Ditampilkan'}</span>
                                </button>

                                <button
                                    type="button"
                                    onClick={() => setFilterOnlyPending(!filterOnlyPending)}
                                    className={`inline-flex items-center gap-1.5 rounded-xl border px-3 py-1.5 text-xs font-semibold transition ${
                                        filterOnlyPending
                                            ? 'border-[#0B57D0] bg-blue-50 text-[#0B57D0]'
                                            : 'border-slate-300 bg-white text-slate-600 hover:bg-slate-50'
                                    }`}
                                >
                                    <Filter className="h-3 w-3" />
                                    <span>Hanya Belum Terkirim</span>
                                </button>

                                <span className="text-xs text-slate-400">
                                    Terpilih: <strong className="text-slate-800 font-mono">{selectedIds.length}</strong> dari {preview.recipients.length}
                                </span>
                            </div>

                            <div className="flex items-center gap-2">
                                <div className="relative">
                                    <Search className="absolute left-3 top-1/2 -translate-y-1/2 h-3.5 w-3.5 text-slate-400" />
                                    <input
                                        type="text"
                                        value={search}
                                        onChange={(e) => setSearch(e.target.value)}
                                        placeholder="Cari nama, NIK, kontak..."
                                        className="rounded-xl border-slate-300 pl-8 pr-3 py-1.5 text-xs shadow-xs focus:border-indigo-500 focus:ring-indigo-500 w-52"
                                    />
                                </div>

                                {departments.length > 0 && (
                                    <select
                                        value={departmentFilter}
                                        onChange={(e) => setDepartmentFilter(e.target.value)}
                                        className="rounded-xl border-slate-300 py-1.5 text-xs shadow-xs focus:border-indigo-500 focus:ring-indigo-500"
                                    >
                                        <option value="all">Semua Departemen</option>
                                        {departments.map((dept) => (
                                            <option key={dept} value={dept}>
                                                {dept}
                                            </option>
                                        ))}
                                    </select>
                                )}
                            </div>
                        </div>

                        {/* Recipient Table */}
                        <div className="overflow-x-auto">
                            <table className="min-w-full divide-y divide-slate-100 text-xs">
                                <thead className="bg-slate-50 font-bold uppercase tracking-wider text-slate-600">
                                    <tr>
                                        <th className="w-10 px-4 py-3 text-center">
                                            <input
                                                type="checkbox"
                                                checked={allFilteredSelected}
                                                onChange={toggleSelectAllFiltered}
                                                className="rounded border-slate-300 text-[#0B57D0] focus:ring-[#0B57D0]"
                                            />
                                        </th>
                                        <th className="px-4 py-3 text-left">Karyawan & Slip</th>
                                        <th className="px-4 py-3 text-left">Departemen</th>
                                        <th className="px-4 py-3 text-left">Email Tujuan</th>
                                        <th className="px-4 py-3 text-left">WhatsApp Tujuan</th>
                                        {can.viewFinancial && (
                                            <th className="px-4 py-3 text-right">Take Home Pay</th>
                                        )}
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-slate-100 bg-white">
                                    {filteredRecipients.length > 0 ? (
                                        filteredRecipients.map((r) => {
                                            const isSelected = selectedIds.includes(r.payslip_id);
                                            const emailSent = r.email_delivery_status === 'sent' || r.email_delivery_status === 'delivered';
                                            const waSent = r.whatsapp_delivery_status === 'sent' || r.whatsapp_delivery_status === 'delivered';

                                            return (
                                                <tr
                                                    key={r.payslip_id}
                                                    onClick={() => toggleSelectOne(r.payslip_id)}
                                                    className={`cursor-pointer transition hover:bg-slate-50/70 ${
                                                        isSelected ? 'bg-blue-50/40' : ''
                                                    }`}
                                                >
                                                    <td className="px-4 py-3 text-center" onClick={(e) => e.stopPropagation()}>
                                                        <input
                                                            type="checkbox"
                                                            checked={isSelected}
                                                            onChange={() => toggleSelectOne(r.payslip_id)}
                                                            className="rounded border-slate-300 text-[#0B57D0] focus:ring-[#0B57D0]"
                                                        />
                                                    </td>
                                                    <td className="px-4 py-3">
                                                        <div className="font-semibold text-slate-800">
                                                            {r.employee_name}
                                                        </div>
                                                        <div className="text-[10px] text-slate-400 font-mono">
                                                            {r.employee_number} • {r.slip_number}
                                                        </div>
                                                    </td>
                                                    <td className="px-4 py-3">
                                                        <span className="font-medium text-slate-700">
                                                            {r.department?.name ?? (typeof r.department === 'string' ? r.department : '-')}
                                                        </span>
                                                        <div className="text-[10px] text-slate-400">
                                                            {r.position?.name ?? (typeof r.position === 'string' ? r.position : '-')}
                                                        </div>
                                                    </td>
                                                    <td className="px-4 py-3">
                                                        {r.is_email_valid ? (
                                                            <div>
                                                                <span className="text-slate-800 font-mono text-[11px] block">
                                                                    {r.email}
                                                                </span>
                                                                {emailSent && (
                                                                    <span className="inline-flex items-center gap-1 rounded bg-emerald-50 px-1.5 py-0.5 text-[10px] font-semibold text-emerald-700">
                                                                        <Check className="h-2.5 w-2.5" /> Sudah Terkirim
                                                                    </span>
                                                                )}
                                                                {r.email_delivery_status === 'queued' && (
                                                                    <span className="inline-flex items-center gap-1 rounded bg-blue-50 px-1.5 py-0.5 text-[10px] font-semibold text-blue-700">
                                                                        <Clock className="h-2.5 w-2.5" /> Dalam Antrean
                                                                    </span>
                                                                )}
                                                                {r.email_delivery_status === 'failed' && (
                                                                    <span className="inline-flex items-center gap-1 rounded bg-rose-50 px-1.5 py-0.5 text-[10px] font-semibold text-rose-700">
                                                                        <AlertCircle className="h-2.5 w-2.5" /> Gagal Sebelumnya
                                                                    </span>
                                                                )}
                                                            </div>
                                                        ) : (
                                                            <span className="inline-flex items-center gap-1 text-[11px] text-rose-600 font-medium">
                                                                <AlertCircle className="h-3 w-3 shrink-0" />
                                                                Email tidak valid
                                                            </span>
                                                        )}
                                                    </td>
                                                    <td className="px-4 py-3">
                                                        {r.is_phone_valid ? (
                                                            <div>
                                                                <span className="text-slate-800 font-mono text-[11px] block">
                                                                    {r.phone}
                                                                </span>
                                                                {waSent && (
                                                                    <span className="inline-flex items-center gap-1 rounded bg-emerald-50 px-1.5 py-0.5 text-[10px] font-semibold text-emerald-700">
                                                                        <Check className="h-2.5 w-2.5" /> Sudah Terkirim
                                                                    </span>
                                                                )}
                                                                {r.whatsapp_delivery_status === 'queued' && (
                                                                    <span className="inline-flex items-center gap-1 rounded bg-blue-50 px-1.5 py-0.5 text-[10px] font-semibold text-blue-700">
                                                                        <Clock className="h-2.5 w-2.5" /> Dalam Antrean
                                                                    </span>
                                                                )}
                                                                {r.whatsapp_delivery_status === 'failed' && (
                                                                    <span className="inline-flex items-center gap-1 rounded bg-rose-50 px-1.5 py-0.5 text-[10px] font-semibold text-rose-700">
                                                                        <AlertCircle className="h-2.5 w-2.5" /> Gagal Sebelumnya
                                                                    </span>
                                                                )}
                                                            </div>
                                                        ) : (
                                                            <span className="inline-flex items-center gap-1 text-[11px] text-rose-600 font-medium">
                                                                <AlertCircle className="h-3 w-3 shrink-0" />
                                                                Nomor tidak valid
                                                            </span>
                                                        )}
                                                    </td>
                                                    {can.viewFinancial && (
                                                        <td className="px-4 py-3 text-right font-mono font-bold text-slate-800">
                                                            {formatRupiah(r.net_salary)}
                                                        </td>
                                                    )}
                                                </tr>
                                            );
                                        })
                                    ) : (
                                        <tr>
                                            <td colSpan={can.viewFinancial ? 6 : 5} className="py-8 text-center text-slate-400">
                                                Tidak ada data penerima yang cocok dengan filter pencarian.
                                            </td>
                                        </tr>
                                    )}
                                </tbody>
                            </table>
                        </div>

                        {/* Sticky Action Footer */}
                        <div className="sticky bottom-0 bg-white border-t border-slate-200 p-4 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 shadow-lg rounded-b-2xl">
                            <div>
                                <span className="text-xs text-slate-500 block">
                                    Siap dimasukkan ke antrean pengiriman latar belakang:
                                </span>
                                <span className="text-sm font-bold text-slate-900">
                                    {selectedIds.length} karyawan terpilih untuk kanal{' '}
                                    <span className="uppercase text-[#0B57D0]">
                                        {channel === 'both' ? 'Email & WhatsApp' : channel}
                                    </span>
                                </span>
                            </div>

                            <button
                                type="button"
                                disabled={selectedIds.length === 0 || submitting}
                                onClick={() => setConfirmModalOpen(true)}
                                className="inline-flex items-center justify-center gap-2 rounded-xl bg-slate-900 px-5 py-2.5 text-xs font-bold text-white shadow-xs hover:bg-slate-800 transition disabled:opacity-50"
                            >
                                <Send className="h-4 w-4" />
                                <span>Jalankan Pengiriman Massal</span>
                            </button>
                        </div>
                    </div>
                ) : (
                    <div className="py-12 text-center text-slate-400 bg-white rounded-2xl border border-dashed border-slate-200">
                        {loadingPreview ? (
                            <div className="flex items-center justify-center gap-2">
                                <RefreshCw className="h-4 w-4 animate-spin text-[#0B57D0]" />
                                <span className="text-sm">Memuat pratinjau data slip gaji dan kesiapan kontak...</span>
                            </div>
                        ) : (
                            <span className="text-sm">Pilih periode gaji di atas untuk menampilkan pratinjau penerima.</span>
                        )}
                    </div>
                )}
            </div>

            {/* Confirmation Modal */}
            {confirmModalOpen && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 backdrop-blur-xs p-4">
                    <div className="w-full max-w-md rounded-2xl bg-white p-6 shadow-xl space-y-4">
                        <div className="flex items-center gap-3">
                            <div className="rounded-xl bg-blue-50 p-2.5 text-[#0B57D0]">
                                <Send className="h-6 w-6" />
                            </div>
                            <div>
                                <h3 className="text-sm font-bold text-slate-900">
                                    Konfirmasi Pengiriman Massal
                                </h3>
                                <p className="text-xs text-slate-500">
                                    Periode: {preview?.period?.period_code}
                                </p>
                            </div>
                        </div>

                        <div className="rounded-xl bg-slate-50 p-4 text-xs text-slate-700 space-y-2 border border-slate-100">
                            <p>
                                Sistem akan membuat pekerjaan antrean terpisah per slip gaji dan per kanal:
                            </p>
                            <ul className="list-disc pl-5 space-y-1">
                                <li>Jumlah slip dipilih: <strong>{selectedIds.length} slip</strong></li>
                                <li>Kanal aktif: <strong>{channel === 'both' ? 'Email & WhatsApp' : channel.toUpperCase()}</strong></li>
                                <li>Pekerjaan diproses secara asinkron melalui antrean database / cron worker.</li>
                            </ul>
                        </div>

                        <div className="flex items-center justify-end gap-2 pt-2">
                            <button
                                type="button"
                                disabled={submitting}
                                onClick={() => setConfirmModalOpen(false)}
                                className="rounded-xl border border-slate-300 bg-white px-4 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50 transition"
                            >
                                Batalkan
                            </button>
                            <button
                                type="button"
                                disabled={submitting}
                                onClick={handleConfirmSubmit}
                                className="inline-flex items-center gap-1.5 rounded-xl bg-slate-900 px-4 py-2 text-xs font-bold text-white shadow-xs hover:bg-slate-800 transition disabled:opacity-50"
                            >
                                {submitting && <RefreshCw className="h-3.5 w-3.5 animate-spin" />}
                                <span>{submitting ? 'Memproses...' : 'Ya, Masukkan ke Antrean'}</span>
                            </button>
                        </div>
                    </div>
                </div>
            )}
        </AuthenticatedLayout>
    );
}
