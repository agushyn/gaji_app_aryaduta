import { useState, useRef } from 'react';
import Checkbox from '@/Components/Checkbox';
import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import TextInput from '@/Components/TextInput';
import GuestLayout from '@/Layouts/GuestLayout';
import { Head, useForm } from '@inertiajs/react';

// Ganti dengan link/path gambar ilustrasi 3D Anda (misal di folder public/images/...)
const BG_IMAGE_URL = '/bgsarah.jpg';

export default function Login({ status }) {
    const [showPassword, setShowPassword] = useState(false);
    const glassCardRef = useRef(null);

    const { data, setData, post, processing, errors, reset } = useForm({
        email: '',
        password: '',
        remember: false,
    });

    // Efek pantulan cahaya Liquid Glass mengikuti gerakan kursor
    const handleMouseMove = (e) => {
        if (!glassCardRef.current) return;
        const rect = glassCardRef.current.getBoundingClientRect();
        const x = ((e.clientX - rect.left) / rect.width) * 100;
        const y = ((e.clientY - rect.top) / rect.height) * 100;
        glassCardRef.current.style.setProperty('--mouse-x', `${x}%`);
        glassCardRef.current.style.setProperty('--mouse-y', `${y}%`);
    };

    const handleMouseLeave = () => {
        if (!glassCardRef.current) return;
        glassCardRef.current.style.setProperty('--mouse-x', '50%');
        glassCardRef.current.style.setProperty('--mouse-y', '25%');
    };

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
            <Head title="Masuk - Sistem Penggajian Arya Duta" />

            {/* Full Screen Container Tanpa Bingkai Hitam */}
            <div className="fixed inset-0 z-50 flex min-h-screen w-full items-center justify-center lg:justify-end overflow-y-auto bg-[#c8e8a7] px-4 py-8 sm:px-8 lg:px-16 xl:px-24 font-sans">

                {/* Background Ilustrasi Full Screen via Source Link */}
                <img
                    src={BG_IMAGE_URL}
                    alt="Ilustrasi Sistem Penggajian Arya Duta"
                    className="fixed inset-0 h-full w-full object-cover object-center lg:object-left select-none pointer-events-none"
                />

                {/* Overlay Gradasi Halus & Cahaya Ambient di Balik Kaca */}
                <div className="pointer-events-none fixed inset-0 bg-gradient-to-r from-[#b5df89]/15 via-transparent to-black/15" />
                <div className="pointer-events-none fixed -top-24 right-1/4 h-96 w-96 rounded-full bg-[#a3e635]/30 blur-3xl" />
                <div className="pointer-events-none fixed -bottom-24 right-12 h-96 w-96 rounded-full bg-emerald-600/20 blur-3xl" />

                {/* Kartu Form Liquid Glass */}
                <div
                    ref={glassCardRef}
                    onMouseMove={handleMouseMove}
                    onMouseLeave={handleMouseLeave}
                    className="liquid-glass-card relative z-10 w-full max-w-[440px] rounded-[28px] sm:rounded-[32px] p-6 sm:p-10"
                >
                    <div className="relative z-10">
                        {/* Brand Logo & Nama Aplikasi */}
                        <div className="mb-6 flex items-center justify-center gap-2.5">
                            <span className="relative flex h-6 w-7 items-center justify-center rounded-[5px] bg-[#9be15d] shadow-sm">
                                <span className="h-2 w-2 rounded-full bg-white/90" />
                                <span className="absolute -bottom-1 left-1.5 h-2 w-2 rotate-45 bg-[#9be15d]" />
                            </span>
                            <span className="text-base font-bold tracking-tight text-slate-900">
                                Sistem Penggajian Arya Duta
                            </span>
                        </div>

                        {/* Heading */}
                        <div className="mb-6 text-center">
                            <h2 className="text-2xl sm:text-[28px] font-bold leading-tight text-slate-900">
                                Masuk ke Akun Anda
                            </h2>
                            <p className="mt-1.5 text-xs sm:text-sm text-slate-700/85">
                                Gunakan kredensial resmi internal untuk melanjutkan
                            </p>
                        </div>

                        {status && (
                            <div className="mb-4 rounded-2xl border border-emerald-300/80 bg-emerald-50/80 p-3 text-xs sm:text-sm font-medium text-emerald-900 backdrop-blur-md">
                                {status}
                            </div>
                        )}

                        {errors.email && (
                            <div className="mb-4 rounded-2xl border border-rose-300/80 bg-rose-50/80 p-3 text-xs sm:text-sm text-rose-900 backdrop-blur-md">
                                {errors.email}
                            </div>
                        )}

                        <form onSubmit={submit} className="space-y-4">
                            <div>
                                <InputLabel
                                    htmlFor="email"
                                    value="Alamat Email"
                                    className="mb-1.5 ms-3 text-xs font-semibold text-slate-800"
                                />
                                <TextInput
                                    id="email"
                                    type="email"
                                    name="email"
                                    value={data.email}
                                    className="liquid-glass-input block w-full rounded-full px-4 py-2.5 text-sm text-slate-900 placeholder-slate-400 focus:ring-0"
                                    autoComplete="username"
                                    placeholder="nama@example.com"
                                    isFocused={true}
                                    onChange={(e) => setData('email', e.target.value)}
                                    required
                                />
                            </div>

                            <div>
                                <div className="flex items-center justify-between">
                                    <InputLabel
                                        htmlFor="password"
                                        value="Kata Sandi"
                                        className="mb-1.5 ms-3 text-xs font-semibold text-slate-800"
                                    />
                                </div>
                                <div className="relative">
                                    <TextInput
                                        id="password"
                                        type={showPassword ? 'text' : 'password'}
                                        name="password"
                                        value={data.password}
                                        className="liquid-glass-input block w-full rounded-full py-2.5 pl-4 pr-11 text-sm text-slate-900 placeholder-slate-400 focus:ring-0"
                                        autoComplete="current-password"
                                        placeholder="••••••••"
                                        onChange={(e) => setData('password', e.target.value)}
                                        required
                                    />
                                    <button
                                        type="button"
                                        onClick={() => setShowPassword(!showPassword)}
                                        className="absolute inset-y-0 right-0 flex items-center pr-4 text-slate-400 hover:text-slate-700 focus:outline-none"
                                        tabIndex={-1}
                                    >
                                        {showPassword ? (
                                            <svg xmlns="http://www.w3.org/2000/svg" className="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                                                <path strokeLinecap="round" strokeLinejoin="round" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21" />
                                            </svg>
                                        ) : (
                                            <svg xmlns="http://www.w3.org/2000/svg" className="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                                                <path strokeLinecap="round" strokeLinejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                                <path strokeLinecap="round" strokeLinejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                            </svg>
                                        )}
                                    </button>
                                </div>
                                <InputError message={errors.password} className="mt-1.5 ms-3" />
                            </div>

                            <div className="flex items-center justify-between pt-1 px-2">
                                <label className="flex items-center cursor-pointer">
                                    <Checkbox
                                        name="remember"
                                        checked={data.remember}
                                        onChange={(e) => setData('remember', e.target.checked)}
                                        className="rounded border-slate-300 text-[#65a30d] focus:ring-[#84cc16]"
                                    />
                                    <span className="ms-2 text-xs sm:text-sm font-medium text-slate-700 select-none">
                                        Ingat sesi saya
                                    </span>
                                </label>
                            </div>

                            <div className="pt-2">
                                <PrimaryButton
                                    className="w-full justify-center !rounded-full !bg-[#9be15d] py-3 px-5 text-xs sm:text-sm !font-bold !text-slate-900 !normal-case !tracking-normal shadow-[0_10px_20px_-5px_rgba(132,204,22,0.5),inset_0_1px_1px_rgba(255,255,255,0.85)] hover:!bg-[#8cd748] transition active:scale-[0.99]"
                                    disabled={processing}
                                >
                                    {processing ? 'Memproses...' : 'Masuk Sekarang'}
                                </PrimaryButton>
                            </div>
                        </form>

                        {/* Bagian Seeder (Uncomment jika ingin diaktifkan) */}
                        {/* <div className="mt-6 border-t border-white/60 pt-4">
                            <p className="text-[11px] font-semibold uppercase tracking-wider text-slate-500">
                                Akun Uji Coba Cepat (Seeder)
                            </p>
                            <div className="mt-2.5 grid grid-cols-2 gap-2 text-xs">
                                <button
                                    type="button"
                                    onClick={() => fillAccount('admin@example.com')}
                                    className="rounded-xl border border-white/80 bg-white/50 px-2.5 py-1.5 text-left font-medium text-slate-700 backdrop-blur-sm hover:bg-white/80 transition"
                                >
                                    <span className="font-semibold block text-indigo-600">Super Admin</span>
                                    admin@example.com
                                </button>
                                <button
                                    type="button"
                                    onClick={() => fillAccount('personalia@example.com')}
                                    className="rounded-xl border border-white/80 bg-white/50 px-2.5 py-1.5 text-left font-medium text-slate-700 backdrop-blur-sm hover:bg-white/80 transition"
                                >
                                    <span className="font-semibold block text-emerald-600">Personalia</span>
                                    personalia@example.com
                                </button>
                                <button
                                    type="button"
                                    onClick={() => fillAccount('approver@example.com')}
                                    className="rounded-xl border border-white/80 bg-white/50 px-2.5 py-1.5 text-left font-medium text-slate-700 backdrop-blur-sm hover:bg-white/80 transition"
                                >
                                    <span className="font-semibold block text-amber-600">Approver</span>
                                    approver@example.com
                                </button>
                                <button
                                    type="button"
                                    onClick={() => fillAccount('auditor@example.com')}
                                    className="rounded-xl border border-white/80 bg-white/50 px-2.5 py-1.5 text-left font-medium text-slate-700 backdrop-blur-sm hover:bg-white/80 transition"
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
                        </div> */}
                    </div>
                </div>

            </div>
        </GuestLayout>
    );
}