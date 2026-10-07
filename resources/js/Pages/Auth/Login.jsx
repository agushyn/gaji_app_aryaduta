import Checkbox from '@/Components/Checkbox';
import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import TextInput from '@/Components/TextInput';
import GuestLayout from '@/Layouts/GuestLayout';
import { Head, useForm } from '@inertiajs/react';

export default function Login({ status }) {
    const { data, setData, post, processing, errors, reset } = useForm({
        email: '',
        password: '',
        remember: false,
    });

    const submit = (e) => {
        e.preventDefault();

        post(route('login'), {
            onFinish: () => reset('password'),
        });
    };

    const fillAccount = (email) => {
        setData({
            email,
            password: 'Password123!',
            remember: true,
        });
    };

    return (
        <GuestLayout>
            <Head title="Masuk - Portal Personalia" />

            <div className="mb-6">
                <h2 className="text-lg font-semibold text-slate-900">
                    Masuk ke Akun Anda
                </h2>
                <p className="mt-1 text-sm text-slate-500">
                    Gunakan kredensial resmi internal untuk melanjutkan
                </p>
            </div>

            {status && (
                <div className="mb-4 rounded-lg bg-emerald-50 p-3 text-sm font-medium text-emerald-800 border border-emerald-200">
                    {status}
                </div>
            )}

            {errors.email && (
                <div className="mb-4 rounded-lg bg-rose-50 p-3 text-sm text-rose-800 border border-rose-200">
                    {errors.email}
                </div>
            )}

            <form onSubmit={submit} className="space-y-4">
                <div>
                    <InputLabel htmlFor="email" value="Alamat Email" />
                    <TextInput
                        id="email"
                        type="email"
                        name="email"
                        value={data.email}
                        className="mt-1.5 block w-full rounded-lg border-slate-300 shadow-sm focus:border-indigo-600 focus:ring-indigo-600 sm:text-sm"
                        autoComplete="username"
                        placeholder="nama@example.com"
                        isFocused={true}
                        onChange={(e) => setData('email', e.target.value)}
                        required
                    />
                </div>

                <div>
                    <div className="flex items-center justify-between">
                        <InputLabel htmlFor="password" value="Kata Sandi" />
                    </div>
                    <TextInput
                        id="password"
                        type="password"
                        name="password"
                        value={data.password}
                        className="mt-1.5 block w-full rounded-lg border-slate-300 shadow-sm focus:border-indigo-600 focus:ring-indigo-600 sm:text-sm"
                        autoComplete="current-password"
                        placeholder="••••••••"
                        onChange={(e) => setData('password', e.target.value)}
                        required
                    />
                    <InputError message={errors.password} className="mt-1.5" />
                </div>

                <div className="flex items-center justify-between pt-1">
                    <label className="flex items-center cursor-pointer">
                        <Checkbox
                            name="remember"
                            checked={data.remember}
                            onChange={(e) => setData('remember', e.target.checked)}
                            className="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500"
                        />
                        <span className="ms-2 text-sm text-slate-600 select-none">
                            Ingat sesi saya
                        </span>
                    </label>
                </div>

                <div className="pt-2">
                    <PrimaryButton
                        className="w-full justify-center rounded-xl bg-[#0B57D0] py-2.5 px-4 text-xs font-semibold text-white shadow-xs hover:bg-[#0A58CA] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#0B57D0] transition"
                        disabled={processing}
                    >
                        {processing ? 'Memproses...' : 'Masuk Sekarang'}
                    </PrimaryButton>
                </div>
            </form>

            <div className="mt-6 border-t border-slate-200 pt-5">
                <p className="text-xs font-semibold uppercase tracking-wider text-slate-400">
                    Akun Uji Coba Cepat (Seeder)
                </p>
                <div className="mt-2.5 grid grid-cols-2 gap-2 text-xs">
                    <button
                        type="button"
                        onClick={() => fillAccount('admin@example.com')}
                        className="rounded border border-slate-200 bg-slate-50 px-2.5 py-1.5 text-left font-medium text-slate-700 hover:bg-slate-100 hover:border-slate-300 transition"
                    >
                        <span className="font-semibold block text-indigo-600">Super Admin</span>
                        admin@example.com
                    </button>
                    <button
                        type="button"
                        onClick={() => fillAccount('personalia@example.com')}
                        className="rounded border border-slate-200 bg-slate-50 px-2.5 py-1.5 text-left font-medium text-slate-700 hover:bg-slate-100 hover:border-slate-300 transition"
                    >
                        <span className="font-semibold block text-emerald-600">Personalia</span>
                        personalia@example.com
                    </button>
                    <button
                        type="button"
                        onClick={() => fillAccount('approver@example.com')}
                        className="rounded border border-slate-200 bg-slate-50 px-2.5 py-1.5 text-left font-medium text-slate-700 hover:bg-slate-100 hover:border-slate-300 transition"
                    >
                        <span className="font-semibold block text-amber-600">Approver</span>
                        approver@example.com
                    </button>
                    <button
                        type="button"
                        onClick={() => fillAccount('auditor@example.com')}
                        className="rounded border border-slate-200 bg-slate-50 px-2.5 py-1.5 text-left font-medium text-slate-700 hover:bg-slate-100 hover:border-slate-300 transition"
                    >
                        <span className="font-semibold block text-cyan-600">Auditor</span>
                        auditor@example.com
                    </button>
                </div>
                <div className="mt-2 text-center">
                    <button
                        type="button"
                        onClick={() => fillAccount('inactive@example.com')}
                        className="text-xs text-rose-600 hover:text-rose-700 hover:underline"
                    >
                        Coba Akun Nonaktif (inactive@example.com)
                    </button>
                </div>
            </div>
        </GuestLayout>
    );
}
