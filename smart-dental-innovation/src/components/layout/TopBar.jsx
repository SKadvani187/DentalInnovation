import React from 'react';

const SOCIAL_LINKS = [
  {
    name: "Facebook",
    iconPath: "M5 3h14a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2m13 2h-2.5A3.5 3.5 0 0 0 12 8.5V11h-2v3h2v7h3v-7h3v-3h-3V9a1 1 0 0 1 1-1h2V5z",
    viewBox: "0 0 24 24"
  },
  {
    name: "Instagram",
    iconPath: "M7.8 2h8.4C19.4 2 22 4.6 22 7.8v8.4a5.8 5.8 0 0 1-5.8 5.8H7.8C4.6 22 2 19.4 2 16.2V7.8A5.8 5.8 0 0 1 7.8 2m-.2 2A3.6 3.6 0 0 0 4 7.6v8.8C4 18.39 5.61 20 7.6 20h8.8a3.6 3.6 0 0 0 3.6-3.6V7.6C20 5.61 18.39 4 16.4 4H7.6m9.65 1.5a1.25 1.25 0 0 1 1.25 1.25A1.25 1.25 0 0 1 17.25 8 1.25 1.25 0 0 1 16 6.75a1.25 1.25 0 0 1 1.25-1.25M12 7a5 5 0 0 1 5 5 5 5 0 0 1-5 5 5 5 0 0 1-5-5 5 5 0 0 1 5-5m0 2a3 3 0 0 0-3 3 3 3 0 0 0 3 3 3 3 0 0 0 3-3 3 3 0 0 0-3-3z",
    viewBox: "0 0 24 24"
  },
  {
    name: "YouTube",
    iconPath: "M10 15l5.19-3L10 9v6m11.56-7.83c.13.47.22 1.1.28 1.9.07.8.1 1.49.1 2.09L22 12c0 2.19-.16 3.8-.44 4.83-.25.9-.83 1.48-1.73 1.73-.47.13-1.33.22-2.65.28-1.3.07-2.49.1-3.59.1L12 19c-4.19 0-6.8-.16-7.83-.44-.9-.25-1.48-.83-1.73-1.73-.13-.47-.22-1.1-.28-1.9-.07-.8-.1-1.49-.1-2.09L2 12c0-2.19.16-3.8.44-4.83.25-.9.83-1.48 1.73-1.73.47-.13 1.33-.22 2.65-.28 1.3-.07 2.49-.1 3.59-.1L12 5c4.19 0 6.8.16 7.83.44.9.25 1.48.83 1.73 1.73z",
    viewBox: "0 0 24 24"
  },
  {
    name: "Google",
    iconPath: "M12.545,10.239v3.821h5.445c-0.712,2.315-2.647,3.972-5.445,3.972c-3.332,0-6.033-2.701-6.033-6.032s2.701-6.032,6.033-6.032c1.498,0,2.866,0.549,3.921,1.453l2.814-2.814C17.503,2.988,15.139,2,12.545,2C7.021,2,2.543,6.477,2.543,12s4.478,10,10.002,10c8.396,0,10.249-7.85,9.426-11.748L12.545,10.239z",
    viewBox: "0 0 24 24"
  },
  {
    name: "LinkedIn",
    iconPath: "M19 3a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h14m-.5 15.5v-5.3a3.26 3.26 0 0 0-3.26-3.26c-.85 0-1.84.52-2.32 1.3v-1.11h-2.79v8.37h2.79v-4.93c0-.77.62-1.4 1.39-1.4a1.4 1.4 0 0 1 1.4 1.4v4.93h2.79M6.88 8.56a1.68 1.68 0 0 0 1.68-1.68c0-.93-.75-1.69-1.68-1.69a1.69 1.69 0 0 0-1.69 1.69c0 .93.76 1.68 1.69 1.68m1.39 9.94v-8.37H5.5v8.37h2.77z",
    viewBox: "0 0 24 24"
  }
];

export function TopBar() {
  return (
    <div className="w-full bg-[var(--background-secondary,#f8f9fa)] border-b border-gray-200">
      {/* Removed px-4 sm:px-6 md:px-8 to align with the left window margin */}
      <div className="max-w-[1200px] mx-auto px-0 flex items-center justify-between flex-wrap py-2.5">
        <div className="flex items-center gap-3 flex-wrap">
          <span className="text-[11px] sm:text-[12px] font-bold tracking-wider text-[var(--text-secondary,#555)] uppercase">
            Stay Connected
          </span>

          <div className="flex items-center gap-1.5">
            {SOCIAL_LINKS.map((social) => (
              <button
                key={social.name}
                type="button"
                aria-label={`Visit our ${social.name}`}
                className="w-7 h-7 sm:w-8 sm:h-8 flex items-center justify-center rounded-full text-gray-500 hover:text-[var(--main,#1976d2)] hover:bg-gray-100 transition-colors cursor-pointer outline-none"
              >
                <svg className="w-[16px] h-[16px] sm:w-[18px] sm:h-[18px]" focusable="false" aria-hidden="true" viewBox={social.viewBox}>
                  <path fill="currentColor" d={social.iconPath} />
                </svg>
              </button>
            ))}
          </div>

          <span className="text-[12px] sm:text-[13px] font-medium text-[var(--text-secondary,#666)] pl-1">
            Over 203k+ Followers
          </span>
        </div>
      </div>
    </div>
  );
}

export default TopBar;