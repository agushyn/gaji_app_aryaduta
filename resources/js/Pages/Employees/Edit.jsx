import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import TextInput from '@/Components/TextInput';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, useForm } from '@inertiajs/react';
import { AlertCircle, ArrowLeft, Edit3, Save } from 'lucide-react';

export default function EmployeesEdit({ employee, offices = [], positions = [], departments = [] }) {
    const { data, setData, put, processing, errors } = useForm({
        employee_number: employee.employee_number || '',
        full_name: employee.full_name || '',
        office_id: employee.office_id ? String(employee.office_id) : (offices.length === 1 ? String(offices[0].id) : ''),
        division_id: employee.division_id ? String(employee.division_id) : '',
        position_id: employee.position_id ? String(employee.position_id) : '',
        department_id: employee.department_id ? String(employee.department_id) : '',
        department: employee.department || '',
        position: employee.position || '',
        email: employee.email || '',
        phone: employee.phone || '',
        hire_date: employee.hire_date ? employee.hire_date.substring(0, 10) : '',
        employment_status: employee.employment_status || 'active',
        gaji_pokok: employee.gaji_pokok ?? 0,
        tunjangan_kehadiran: employee.tunjangan_kehadiran ?? 0,
        tunjangan_keluarga: employee.tunjangan_keluarga ?? 0,
        tunjangan_jabatan: employee.tunjangan_jabatan ?? 0,
        uang_makan_per_hari: employee.uang_makan_per_hari ?? 0,
        uang_transportasi_per_hari: employee.uang_transportasi_per_hari ?? 0,
        bpjs_ketenagakerjaan_enabled: Boolean(employee.bpjs_ketenagakerjaan_enabled),
        bpjs_ketenagakerjaan_amount: employee.bpjs_ketenagakerjaan_amount ?? 0,
        default_overtime_rate: employee.default_overtime_rate ?? 0,
    });

    const selectedOffice = offices.find(
        (o) => String(o.id) === String(data.office_id)
    );
    const availableDivisions = selectedOffice?.divisions || [];

    const handleOfficeChange = (e) => {
        const selectedOfficeId = e.target.value;
        setData((prev) => ({
            ...prev,
            office_id: selectedOfficeId,
            division_id: '',
        }));
    };

    const handleDivisionChange = (e) => {
        const selectedDivId = e.target.value;
        const selectedDiv = availableDivisions.find((d) => String(d.id) === String(selectedDivId));
        setData((prev) => ({
            ...prev,
            division_id: selectedDivId,
            department_id: selectedDiv?.department_id ? String(selectedDiv.department_id) : prev.department_id,
            department: selectedDiv?.department?.name || prev.department,
        }));
    };

    const handlePositionChange = (e) => {
        const selectedPosId = e.target.value;
        const selectedPos = positions.find((p) => String(p.id) === String(selectedPosId));
        setData((prev) => ({
            ...prev,
            position_id: selectedPosId,
            position: selectedPos ? selectedPos.name : prev.position,
        }));
    };

    const submit = (e) => {
        e.preventDefault();
        put(route('employees.update', employee.id));
    };

    return (
        <AuthenticatedLayout header={`Ubah Karyawan: ${employee.full_name}`}>
            <Head title={`Edit ${employee.full_name}`} />

            <div className="max-w-4xl mx-auto space-y-6">
                {/* Navigation Back */}
                <div className="flex items-center gap-3">
                    <Link
                        href={route('employees.show', employee.id)}
                        className="inline-flex items-center gap-1.5 text-xs font-semibold text-slate-500 hover:text-slate-900 transition"
                    >
                        <ArrowLeft className="h-4 w-4" />
                        <span>Kembali ke Detail Karyawan</span>
                    </Link>
                </div>

                {/* Snapshot Security Note */}
                <div className="rounded-xl border border-amber-200 bg-amber-50/80 p-4 text-xs text-amber-900 flex items-start gap-3">
                    <AlertCircle className="h-4 w-4 text-amber-600 mt-0.5 shrink-0" />
                    <div>
                        <span className="font-semibold block">Integritas Dokumen Historis:</span>
                        Perubahan informasi kontak, nama, kantor, divisi, atau jabatan di sini tidak akan mengubah snapshot penerima pada slip gaji yang telah disetujui dan difinalisasi pada periode sebelumnya.
                    </div>
                </div>

                {/* Form Card */}
                <div className="rounded-2xl border border-slate-200 bg-white p-6 sm:p-8 shadow-xs">
                    <div className="border-b border-slate-100 pb-5">
                        <div className="flex items-center gap-2 text-indigo-600 font-semibold text-xs uppercase tracking-wider">
                            <Edit3 className="h-4 w-4" />
                            <span>Edit Data Master</span>
                        </div>
                        <h1 className="mt-1 text-xl font-bold text-slate-900">
                            Perbarui Data Karyawan
                        </h1>
                        <p className="mt-1 text-xs text-slate-500">
                            Perubahan akan dicatat dalam audit trail sistem.
                        </p>
                    </div>

                    <form onSubmit={submit} className="mt-6 space-y-6">
                        {/* Section: Identitas */}
                        <div className="space-y-4">
                            <h2 className="text-sm font-bold text-slate-900 border-b border-slate-100 pb-2">
                                1. Identitas, Penempatan & Jabatan
                            </h2>

                            <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div>
                                    <InputLabel htmlFor="employee_number" value="Nomor Induk Karyawan (NIK) *" />
                                    <TextInput
                                        id="employee_number"
                                        type="text"
                                        value={data.employee_number}
                                        onChange={(e) => setData('employee_number', e.target.value)}
                                        className="mt-1 block w-full text-xs font-mono"
                                        required
                                    />
                                    <InputError message={errors.employee_number} className="mt-1" />
                                </div>

                                <div>
                                    <InputLabel htmlFor="full_name" value="Nama Lengkap *" />
                                    <TextInput
                                        id="full_name"
                                        type="text"
                                        value={data.full_name}
                                        onChange={(e) => setData('full_name', e.target.value)}
                                        className="mt-1 block w-full text-xs"
                                        required
                                    />
                                    <InputError message={errors.full_name} className="mt-1" />
                                </div>

                                <div>
                                    <InputLabel htmlFor="office_id" value="Kantor *" />
                                    <select
                                        id="office_id"
                                        value={data.office_id}
                                        onChange={handleOfficeChange}
                                        className="mt-1 block w-full rounded-md border-slate-300 shadow-xs focus:border-indigo-600 focus:ring-indigo-600 text-xs font-medium"
                                        required
                                    >
                                        <option value="">Pilih Kantor</option>
                                        {offices?.map((off) => (
                                            <option key={off.id} value={off.id}>
                                                {off.name} ({off.code})
                                            </option>
                                        ))}
                                    </select>
                                    <InputError message={errors.office_id} className="mt-1" />
                                </div>

                                <div>
                                    <InputLabel htmlFor="division_id" value="Divisi *" />
                                    <select
                                        id="division_id"
                                        value={data.division_id}
                                        onChange={handleDivisionChange}
                                        disabled={!data.office_id || availableDivisions.length === 0}
                                        className="mt-1 block w-full rounded-md border-slate-300 shadow-xs focus:border-indigo-600 focus:ring-indigo-600 text-xs font-medium disabled:bg-slate-100 disabled:text-slate-400"
                                        required
                                    >
                                        <option value="">
                                            {!data.office_id
                                                ? 'Pilih Kantor Terlebih Dahulu'
                                                : availableDivisions.length === 0
                                                ? 'Belum ada divisi pada kantor ini'
                                                : 'Pilih Divisi'}
                                        </option>
                                        {availableDivisions.map((div) => (
                                            <option key={div.id} value={div.id}>
                                                {div.name} {div.code ? `(${div.code})` : ''}
                                            </option>
                                        ))}
                                    </select>
                                    <InputError message={errors.division_id} className="mt-1" />
                                </div>

                                <div className="sm:col-span-2">
                                    <InputLabel htmlFor="position_id" value="Jabatan *" />
                                    <select
                                        id="position_id"
                                        value={data.position_id}
                                        onChange={handlePositionChange}
                                        className="mt-1 block w-full rounded-md border-slate-300 shadow-xs focus:border-indigo-600 focus:ring-indigo-600 text-xs font-medium"
                                        required
                                    >
                                        <option value="">Pilih Jabatan</option>
                                        {positions?.map((pos) => (
                                            <option key={pos.id} value={pos.id}>
                                                {pos.name} {pos.code ? `(${pos.code})` : ''}
                                            </option>
                                        ))}
                                    </select>
                                    <InputError message={errors.position_id} className="mt-1" />
                                </div>
                            </div>
                        </div>

                        {/* Section: Kontak */}
                        <div className="space-y-4 pt-2">
                            <h2 className="text-sm font-bold text-slate-900 border-b border-slate-100 pb-2">
                                2. Kontak Pengiriman Slip
                            </h2>

                            <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div>
                                    <InputLabel htmlFor="email" value="Alamat Email Karyawan" />
                                    <TextInput
                                        id="email"
                                        type="email"
                                        value={data.email}
                                        onChange={(e) => setData('email', e.target.value)}
                                        className="mt-1 block w-full text-xs"
                                    />
                                    <InputError message={errors.email} className="mt-1" />
                                </div>

                                <div>
                                    <InputLabel htmlFor="phone" value="Nomor WhatsApp" />
                                    <TextInput
                                        id="phone"
                                        type="text"
                                        value={data.phone}
                                        onChange={(e) => setData('phone', e.target.value)}
                                        className="mt-1 block w-full text-xs font-mono"
                                        placeholder="08123456789 atau 628123456789"
                                    />
                                    <InputError message={errors.phone} className="mt-1" />
                                </div>
                            </div>
                        </div>

                        {/* Section: Status */}
                        <div className="space-y-4 pt-2">
                            <h2 className="text-sm font-bold text-slate-900 border-b border-slate-100 pb-2">
                                3. Status Kepegawaian
                            </h2>

                            <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div>
                                    <InputLabel htmlFor="hire_date" value="Tanggal Mulai Bekerja" />
                                    <TextInput
                                        id="hire_date"
                                        type="date"
                                        value={data.hire_date}
                                        onChange={(e) => setData('hire_date', e.target.value)}
                                        className="mt-1 block w-full text-xs"
                                    />
                                    <InputError message={errors.hire_date} className="mt-1" />
                                </div>

                                <div>
                                    <InputLabel htmlFor="employment_status" value="Status Keaktifan *" />
                                    <select
                                        id="employment_status"
                                        value={data.employment_status}
                                        onChange={(e) => setData('employment_status', e.target.value)}
                                        className="mt-1 block w-full rounded-md border-slate-300 shadow-xs focus:border-indigo-600 focus:ring-indigo-600 text-xs"
                                        required
                                    >
                                        <option value="active">Aktif (Menerima Penggajian)</option>
                                        <option value="inactive">Nonaktif (Ditangguhkan / Berhenti)</option>
                                    </select>
                                    <InputError message={errors.employment_status} className="mt-1" />
                                </div>
                            </div>
                        </div>

                        {/* Section: Komponen Gaji & Tunjangan */}
                        <div className="space-y-6 pt-2">
                            <div>
                                <h2 className="text-sm font-bold text-slate-900 border-b border-slate-100 pb-2">
                                    4. Komponen Gaji & Tunjangan Master
                                </h2>
                                <p className="mt-1 text-xs text-slate-500">
                                    Nilai master ini akan digunakan secara otomatis saat Personalia menjalankan proses Auto Generate Payroll.
                                </p>
                            </div>

                            {/* 4a. Komponen Gaji Tetap Bulanan */}
                            <div className="rounded-xl border border-slate-200 bg-slate-50/50 p-4 space-y-4">
                                <div className="border-b border-slate-200/60 pb-2">
                                    <h3 className="text-xs font-bold text-slate-800 uppercase tracking-wide">
                                        Komponen Tetap (Bulanan)
                                    </h3>
                                    <p className="text-[11px] text-slate-500">
                                        Nominal tetap per bulan dan tidak dikalikan dengan jumlah hari kerja.
                                    </p>
                                </div>

                                <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                    <div>
                                        <InputLabel htmlFor="gaji_pokok" value="Gaji Pokok (Rp) *" />
                                        <TextInput
                                            id="gaji_pokok"
                                            type="number"
                                            min="0"
                                            step="1000"
                                            value={data.gaji_pokok}
                                            onChange={(e) => setData('gaji_pokok', e.target.value)}
                                            className="mt-1 block w-full text-xs font-mono"
                                            placeholder="0"
                                            required
                                        />
                                        <InputError message={errors.gaji_pokok} className="mt-1" />
                                    </div>

                                    <div>
                                        <InputLabel htmlFor="tunjangan_kehadiran" value="Tunjangan Kehadiran (Rp) *" />
                                        <TextInput
                                            id="tunjangan_kehadiran"
                                            type="number"
                                            min="0"
                                            step="1000"
                                            value={data.tunjangan_kehadiran}
                                            onChange={(e) => setData('tunjangan_kehadiran', e.target.value)}
                                            className="mt-1 block w-full text-xs font-mono"
                                            placeholder="0"
                                            required
                                        />
                                        <InputError message={errors.tunjangan_kehadiran} className="mt-1" />
                                    </div>

                                    <div>
                                        <InputLabel htmlFor="tunjangan_keluarga" value="Tunjangan Keluarga (Rp) *" />
                                        <TextInput
                                            id="tunjangan_keluarga"
                                            type="number"
                                            min="0"
                                            step="1000"
                                            value={data.tunjangan_keluarga}
                                            onChange={(e) => setData('tunjangan_keluarga', e.target.value)}
                                            className="mt-1 block w-full text-xs font-mono"
                                            placeholder="0"
                                            required
                                        />
                                        <InputError message={errors.tunjangan_keluarga} className="mt-1" />
                                    </div>

                                    <div>
                                        <InputLabel htmlFor="tunjangan_jabatan" value="Tunjangan Jabatan (Rp) *" />
                                        <TextInput
                                            id="tunjangan_jabatan"
                                            type="number"
                                            min="0"
                                            step="1000"
                                            value={data.tunjangan_jabatan}
                                            onChange={(e) => setData('tunjangan_jabatan', e.target.value)}
                                            className="mt-1 block w-full text-xs font-mono"
                                            placeholder="0"
                                            required
                                        />
                                        <InputError message={errors.tunjangan_jabatan} className="mt-1" />
                                    </div>
                                </div>
                            </div>

                            {/* 4b. Tarif Harian */}
                            <div className="rounded-xl border border-indigo-100 bg-indigo-50/30 p-4 space-y-4">
                                <div className="border-b border-indigo-100 pb-2">
                                    <h3 className="text-xs font-bold text-indigo-900 uppercase tracking-wide">
                                        Tarif Harian (Dikalikan Hari Kerja Periode)
                                    </h3>
                                    <p className="text-[11px] text-slate-500">
                                        Dihitung otomatis: tarif per hari dikalikan jumlah hari kerja yang diperhitungkan.
                                    </p>
                                </div>

                                <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                    <div>
                                        <InputLabel htmlFor="uang_makan_per_hari" value="Tarif Uang Makan / Hari (Rp) *" />
                                        <TextInput
                                            id="uang_makan_per_hari"
                                            type="number"
                                            min="0"
                                            step="1000"
                                            value={data.uang_makan_per_hari}
                                            onChange={(e) => setData('uang_makan_per_hari', e.target.value)}
                                            className="mt-1 block w-full text-xs font-mono"
                                            placeholder="0"
                                            required
                                        />
                                        <InputError message={errors.uang_makan_per_hari} className="mt-1" />
                                    </div>

                                    <div>
                                        <InputLabel htmlFor="uang_transportasi_per_hari" value="Tarif Transportasi / Hari (Rp) *" />
                                        <TextInput
                                            id="uang_transportasi_per_hari"
                                            type="number"
                                            min="0"
                                            step="1000"
                                            value={data.uang_transportasi_per_hari}
                                            onChange={(e) => setData('uang_transportasi_per_hari', e.target.value)}
                                            className="mt-1 block w-full text-xs font-mono"
                                            placeholder="0"
                                            required
                                        />
                                        <InputError message={errors.uang_transportasi_per_hari} className="mt-1" />
                                    </div>
                                </div>
                            </div>

                            {/* 4c. Upah Lembur Default */}
                            <div className="rounded-xl border border-amber-200 bg-amber-50/30 p-4 space-y-4">
                                <div className="border-b border-amber-200/60 pb-2">
                                    <h3 className="text-xs font-bold text-amber-900 uppercase tracking-wide">
                                        Upah Lembur Default
                                    </h3>
                                    <p className="text-[11px] text-slate-500">
                                        Tarif lembur default karyawan per jam.
                                    </p>
                                </div>

                                <div className="max-w-xs">
                                    <InputLabel htmlFor="default_overtime_rate" value="Upah Lembur Default (Rp / Jam)" />
                                    <div className="relative mt-1">
                                        <span className="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-xs text-slate-500 font-semibold">
                                            Rp
                                        </span>
                                        <TextInput
                                            id="default_overtime_rate"
                                            type="number"
                                            min="0"
                                            step="1000"
                                            value={data.default_overtime_rate}
                                            onChange={(e) => setData('default_overtime_rate', e.target.value)}
                                            className="pl-9 block w-full text-xs font-mono"
                                            placeholder="0"
                                        />
                                    </div>
                                    <p className="mt-1 text-[11px] text-slate-500">
                                        Tarif lembur default karyawan per jam.
                                    </p>
                                    <InputError message={errors.default_overtime_rate} className="mt-1" />
                                </div>
                            </div>

                            {/* 4d. BPJS Ketenagakerjaan */}
                            <div className="rounded-xl border border-rose-100 bg-rose-50/20 p-4 space-y-4">
                                <div className="border-b border-rose-100 pb-2">
                                    <h3 className="text-xs font-bold text-slate-800 uppercase tracking-wide">
                                        Potongan BPJS Ketenagakerjaan
                                    </h3>
                                    <p className="text-[11px] text-slate-500">
                                        Potongan tetap berdasarkan nominal masukan Personalia.
                                    </p>
                                </div>

                                <div className="space-y-3">
                                    <label className="flex items-center gap-3 cursor-pointer">
                                        <input
                                            type="checkbox"
                                            checked={Boolean(data.bpjs_ketenagakerjaan_enabled)}
                                            onChange={(e) => {
                                                const checked = e.target.checked;
                                                setData((prev) => ({
                                                    ...prev,
                                                    bpjs_ketenagakerjaan_enabled: checked,
                                                    bpjs_ketenagakerjaan_amount: checked ? prev.bpjs_ketenagakerjaan_amount : 0,
                                                }));
                                            }}
                                            className="rounded border-slate-300 text-indigo-600 focus:ring-indigo-600 h-4 w-4"
                                        />
                                        <span className="text-xs font-medium text-slate-700">
                                            Aktifkan Potongan BPJS Ketenagakerjaan untuk Karyawan Ini
                                        </span>
                                    </label>
                                    <InputError message={errors.bpjs_ketenagakerjaan_enabled} />

                                    {data.bpjs_ketenagakerjaan_enabled && (
                                        <div className="pt-2 max-w-sm">
                                            <InputLabel htmlFor="bpjs_ketenagakerjaan_amount" value="Nomor Potongan BPJS Ketenagakerjaan (Rp) *" />
                                            <TextInput
                                                id="bpjs_ketenagakerjaan_amount"
                                                type="number"
                                                min="0"
                                                step="1000"
                                                value={data.bpjs_ketenagakerjaan_amount}
                                                onChange={(e) => setData('bpjs_ketenagakerjaan_amount', e.target.value)}
                                                className="mt-1 block w-full text-xs font-mono"
                                                placeholder="Contoh: 150000"
                                                required
                                            />
                                            <p className="mt-1 text-[11px] text-slate-500">
                                                Nominal ini akan otomatis dimasukkan pada kolom Potongan Slip Gaji.
                                            </p>
                                            <InputError message={errors.bpjs_ketenagakerjaan_amount} className="mt-1" />
                                        </div>
                                    )}
                                </div>
                            </div>
                        </div>

                        {/* Submit Actions */}
                        <div className="mt-8 flex items-center justify-end gap-3 border-t border-slate-100 pt-5">
                            <Link
                                href={route('employees.show', employee.id)}
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
