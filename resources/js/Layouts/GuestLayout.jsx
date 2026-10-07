import ApplicationLogo from '@/Components/ApplicationLogo';
import { Link } from '@inertiajs/react';

export default function GuestLayout({ children }) {
    return (
        <div className="flex min-h-screen flex-col items-center justify-center bg-[#F4F7FB] px-4 py-8 sm:px-6 lg:px-8">
            <div className="w-full max-w-md">
                <div className="mb-6 flex flex-col items-center text-center">
                    <Link href="/" className="flex items-center gap-3 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#0B57D0] rounded-xl p-1">
                        <ApplicationLogo className="h-12 w-12 text-[#0B57D0]" />
                        <div className="text-left">
                            <h1 className="text-xl font-bold text-slate-900 tracking-tight">Kirim Slip Gaji</h1>
                            <p className="text-xs font-medium text-slate-500">Portal Internal Personalia</p>
                        </div>
                    </Link>
                </div>

                <div className="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm sm:p-8">
                    {children}
                </div>

                <div className="mt-8 text-center text-xs text-slate-500">
                    <p>&copy; {new Date().getFullYear()} Aplikasi Kirim Slip Gaji Personalia. Hak cipta dilindungi.</p>
                    <p className="mt-1">Akses hanya diperbolehkan untuk petugas personalia dan manajemen berwenang.</p>
                </div>
            </div>
        </div>
    );
}
