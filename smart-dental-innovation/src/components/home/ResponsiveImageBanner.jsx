import React from 'react';
import { useSettings } from '../../context/SettingsContext';

// Trust badges strip — admin-managed (Settings → Trust Badges). FA icons + Poppins.
export default function ResponsiveImageBanner() {
  const { trustBadges, liveCounts } = useSettings();
  const badges = Array.isArray(trustBadges) ? trustBadges : [];

  // Real-time product count from the DB (falls back to a marketing number if unavailable).
  const productCount = liveCounts?.products ? `${liveCounts.products}` : "1,000+";

  // dynamic:"productCount" -> prefix the live product count to the label
  const labelFor = (b) => (b.dynamic === "productCount" ? `${productCount} ${b.label}` : b.label);

  return (
    <div className="w-full px-4 sm:px-8 md:px-12 py-4" style={{ fontFamily: "'Poppins', sans-serif" }}>
      <div className="max-w-[1400px] mx-auto">
        <div className="rounded-2xl bg-[#e8f3fb] px-4 sm:px-8 py-5">
          <div className="flex flex-wrap sm:flex-nowrap items-center justify-around gap-y-4">
            {badges.map((b, i) => (
              <div key={i} className="flex items-center gap-3 justify-center px-2">
                <i className={`${b.icon || 'fa-solid fa-circle-check'} text-2xl text-gray-900`} aria-hidden="true"></i>
                <span className="text-sm sm:text-[15px] font-bold text-gray-900 whitespace-nowrap">{labelFor(b)}</span>
              </div>
            ))}
          </div>
        </div>
      </div>
    </div>
  );
}
