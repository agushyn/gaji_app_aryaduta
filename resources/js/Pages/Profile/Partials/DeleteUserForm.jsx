import DangerButton from '@/Components/DangerButton';
import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import Modal from '@/Components/Modal';
import SecondaryButton from '@/Components/SecondaryButton';
import TextInput from '@/Components/TextInput';
import { useForm } from '@inertiajs/react';
import { useRef, useState } from 'react';

export default function DeleteUserForm({ className = '' }) {
    const [confirmingUserDeletion, setConfirmingUserDeletion] = useState(false);
    const passwordInput = useRef();

    const {
        data,
        setData,
        delete: destroy,
        processing,
        reset,
        errors,
        clearErrors,
    } = useForm({
        password: '',
    });

    const confirmUserDeletion = () => {
        setConfirmingUserDeletion(true);
    };

    const deleteUser = (e) => {
        e.preventDefault();

        destroy(route('profile.destroy'), {
            preserveScroll: true,
            onSuccess: () => closeModal(),
            onError: () => passwordInput.current.focus(),
            onFinish: () => reset(),
        });
    };

    const closeModal = () => {
        setConfirmingUserDeletion(false);

        clearErrors();
        reset();
    };

    return (
        <section className={`space-y-4 ${className}`}>
            <header>
                <h2 className="text-base font-bold text-rose-800">
                    Hapus Akun Pengguna
                </h2>

                <p className="mt-1 text-xs text-slate-500">
                    Setelah akun dihapus, akun tidak akan dapat digunakan lagi untuk masuk ke sistem.
                </p>
            </header>

            <DangerButton onClick={confirmUserDeletion}>
                Hapus Akun Saya
            </DangerButton>

            <Modal show={confirmingUserDeletion} onClose={closeModal}>
                <form onSubmit={deleteUser} className="p-6">
                    <h2 className="text-base font-bold text-slate-900">
                        Apakah Anda yakin ingin menghapus akun Anda?
                    </h2>

                    <p className="mt-1 text-xs text-slate-500">
                        Masukkan kata sandi Anda saat ini untuk mengonfirmasi bahwa Anda benar-benar ingin menghapus akun secara permanen.
                    </p>

                    <div className="mt-4">
                        <InputLabel
                            htmlFor="delete_password"
                            value="Kata Sandi"
                            className="sr-only"
                        />

                        <TextInput
                            id="delete_password"
                            type="password"
                            name="password"
                            ref={passwordInput}
                            value={data.password}
                            onChange={(e) =>
                                setData('password', e.target.value)
                            }
                            className="mt-1 block w-3/4 text-xs"
                            isFocused
                            placeholder="Masukkan kata sandi untuk konfirmasi"
                        />

                        <InputError
                            message={errors.password}
                            className="mt-1"
                        />
                    </div>

                    <div className="mt-6 flex justify-end gap-3">
                        <SecondaryButton onClick={closeModal}>
                            Batal
                        </SecondaryButton>

                        <DangerButton disabled={processing}>
                            {processing ? 'Menghapus...' : 'Konfirmasi Hapus Akun'}
                        </DangerButton>
                    </div>
                </form>
            </Modal>
        </section>
    );
}
