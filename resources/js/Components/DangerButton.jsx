export default function DangerButton({
    className = '',
    disabled,
    children,
    ...props
}) {
    return (
        <button
            {...props}
            className={
                `inline-flex items-center justify-center rounded-xl bg-[#EF4444] px-4 py-2.5 text-xs font-semibold text-white shadow-xs transition duration-150 ease-in-out hover:bg-red-600 focus:outline-none focus:ring-2 focus:ring-[#EF4444] focus:ring-offset-2 disabled:opacity-50 ${
                    disabled && 'opacity-50 cursor-not-allowed'
                } ` + className
            }
            disabled={disabled}
        >
            {children}
        </button>
    );
}
