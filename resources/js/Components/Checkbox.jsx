export default function Checkbox({ className = '', ...props }) {
    return (
        <input
            {...props}
            type="checkbox"
            className={
                'rounded-md border-slate-300 text-[#0B57D0] shadow-xs focus:ring-[#0B57D0] ' +
                className
            }
        />
    );
}
