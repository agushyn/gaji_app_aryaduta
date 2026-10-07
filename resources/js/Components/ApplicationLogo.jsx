export default function ApplicationLogo({ className = 'h-10 w-10 text-indigo-600', ...props }) {
    return (
        <svg
            className={className}
            viewBox="0 0 48 48"
            fill="none"
            xmlns="http://www.w3.org/2000/svg"
            {...props}
        >
            <rect width="48" height="48" rx="12" fill="currentColor" fillOpacity="0.1" />
            <path
                d="M16 12C14.8954 12 14 12.8954 14 14V34C14 35.1046 14.8954 36 16 36H32C33.1046 36 34 35.1046 34 34V18L28 12H16Z"
                stroke="currentColor"
                strokeWidth="2.5"
                strokeLinecap="round"
                strokeLinejoin="round"
            />
            <path
                d="M28 12V18H34"
                stroke="currentColor"
                strokeWidth="2.5"
                strokeLinecap="round"
                strokeLinejoin="round"
            />
            <path
                d="M19 24H29M19 28H25"
                stroke="currentColor"
                strokeWidth="2.5"
                strokeLinecap="round"
                strokeLinejoin="round"
            />
            <circle cx="32" cy="32" r="5" fill="#10B981" />
            <path
                d="M30.5 32L31.5 33L33.5 31"
                stroke="white"
                strokeWidth="1.5"
                strokeLinecap="round"
                strokeLinejoin="round"
            />
        </svg>
    );
}
