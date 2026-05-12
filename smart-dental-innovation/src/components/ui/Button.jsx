const base = "inline-flex items-center justify-center gap-2 font-semibold rounded-lg transition disabled:opacity-50 disabled:cursor-not-allowed focus:outline-none focus:ring-2 focus:ring-offset-1";

const variants = {
  primary: "bg-brand-orange text-white hover:bg-brand-orange-light focus:ring-brand-orange",
  navy: "bg-brand-navy text-white hover:bg-brand-navy-light focus:ring-brand-navy",
  outline: "border border-gray-300 bg-white text-brand-ink hover:border-brand-navy focus:ring-brand-navy",
  ghost: "text-brand-ink hover:bg-gray-100 focus:ring-gray-300",
};

const sizes = {
  sm: "text-xs px-3 py-1.5",
  md: "text-sm px-4 py-2",
  lg: "text-base px-5 py-3",
};

export default function Button({
  variant = "primary",
  size = "md",
  className = "",
  children,
  ...props
}) {
  return (
    <button
      className={`${base} ${variants[variant]} ${sizes[size]} ${className}`}
      {...props}
    >
      {children}
    </button>
  );
}
