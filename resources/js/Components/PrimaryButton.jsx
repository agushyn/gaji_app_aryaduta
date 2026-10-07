export default function PrimaryButton({
    className = '',
    disabled,
    children,
    ...props
}) {
    return (
        <button
            {...props}
            className={
                `inline-flex items-center justify-center rounded-xl bg-[#0B57D0] px-4 py-2.5 text-xs font-semibold text-white shadow-xs transition duration-150 ease-in-out hover:bg-[#0A58CA] focus:outline-none focus:ring-2 focus:ring-[#0B57D0] focus:ring-offset-2 disabled:opacity-50 ${
                    disabled && 'opacity-50 cursor-not-allowed'
                } ` + className
            }
            disabled={disabled}
        >
            {children}
        </button>
    );
}
