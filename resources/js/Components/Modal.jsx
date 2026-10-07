import { Dialog, DialogBackdrop, DialogPanel } from '@headlessui/react';

export default function Modal({
    children,
    show = false,
    maxWidth = '2xl',
    closeable = true,
    onClose = () => {},
    className = '',
}) {
    const close = () => {
        if (closeable) {
            onClose();
        }
    };

    const maxWidthClass = {
        sm: 'sm:max-w-sm',
        md: 'sm:max-w-md',
        lg: 'sm:max-w-lg',
        xl: 'sm:max-w-xl',
        '2xl': 'sm:max-w-2xl',
        '3xl': 'sm:max-w-3xl',
        '4xl': 'sm:max-w-4xl',
        '5xl': 'sm:max-w-5xl',
    }[maxWidth] || 'sm:max-w-2xl';

    return (
        <Dialog
            as="div"
            id="modal"
            open={show}
            onClose={close}
            className="relative z-50"
        >
            {/* Backdrop: positioned at z-40 strictly behind dialog content */}
            <DialogBackdrop
                transition
                className="fixed inset-0 z-40 bg-slate-900/50 backdrop-blur-xs transition-opacity duration-300 ease-out data-[closed]:opacity-0 data-[leave]:duration-200 data-[leave]:ease-in"
            />

            {/* Scrollable Container: centered layout on top of backdrop at z-50 */}
            <div className="fixed inset-0 z-50 w-screen overflow-y-auto">
                <div className="flex min-h-full items-center justify-center p-4 text-center sm:p-0">
                    <DialogPanel
                        transition
                        className={`relative z-50 w-full transform overflow-hidden rounded-2xl bg-white text-left shadow-2xl transition-all duration-300 ease-out pointer-events-auto opacity-100 data-[closed]:translate-y-4 data-[closed]:opacity-0 sm:my-8 sm:data-[closed]:translate-y-0 sm:data-[closed]:scale-95 ${maxWidthClass} ${className}`}
                    >
                        {children}
                    </DialogPanel>
                </div>
            </div>
        </Dialog>
    );
}
