export default function InputLabel({
    value,
    className = '',
    children,
    ...props
}) {
    return (
        <label
            {...props}
            className={
                `block text-xs font-semibold text-slate-700 mb-1 ` +
                className
            }
        >
            {value ? value : children}
        </label>
    );
}
