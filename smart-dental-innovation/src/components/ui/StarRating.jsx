export default function StarRating({ value = 0, reviews, size = 14 }) {
  const full = Math.floor(value);
  const half = value - full >= 0.5;
  return (
    <div className="flex items-center gap-1">
      <div className="flex" style={{ color: "#f59e0b" }}>
        {Array.from({ length: 5 }).map((_, i) => {
          const filled = i < full || (i === full && half);
          return (
            <svg key={i} width={size} height={size} viewBox="0 0 24 24" fill={filled ? "currentColor" : "none"} stroke="currentColor" strokeWidth="1.5">
              <path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77 5.82 21l1.18-6.88-5-4.87 6.91-1.01z" />
            </svg>
          );
        })}
      </div>
      {reviews != null && (
        <span className="text-xs text-brand-muted">({reviews})</span>
      )}
    </div>
  );
}
