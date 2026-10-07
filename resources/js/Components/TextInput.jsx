import { forwardRef, useEffect, useImperativeHandle, useRef } from 'react';

export default forwardRef(function TextInput(
    { type = 'text', className = '', isFocused = false, ...props },
    ref,
) {
    const localRef = useRef(null);

    useImperativeHandle(ref, () => ({
        focus: () => localRef.current?.focus(),
    }));

    useEffect(() => {
        if (isFocused) {
            localRef.current?.focus();
        }
    }, [isFocused]);

    return (
        <input
            {...props}
            type={type}
            className={
                'rounded-xl border border-slate-300 bg-slate-50/60 px-3.5 py-2.5 text-xs text-slate-900 shadow-xs focus:bg-white focus:border-[#0B57D0] focus:ring-2 focus:ring-[#0B57D0]/20 focus:outline-none transition ' +
                className
            }
            ref={localRef}
        />
    );
});
