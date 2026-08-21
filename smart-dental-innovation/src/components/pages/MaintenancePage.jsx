import { useSettings } from "../../context/SettingsContext";

export default function MaintenancePage() {
  const { maintenanceMode = {}, company = {} } = useSettings();
  const title = maintenanceMode.title || "We'll be back soon";
  const message =
    maintenanceMode.message ||
    "Our site is undergoing scheduled maintenance. Please check back in a little while.";
  const brand = company.name || "Smart Dental Innovation";
  const supportEmail = company.email || "";
  const supportPhone = company.phone || "";

  return (
    <div className="min-h-screen flex items-center justify-center bg-gradient-to-br from-[#0d0d10] via-[#15151c] to-[#0d0d10] px-4 py-12">
      <div className="max-w-xl w-full bg-white/[0.03] border border-white/10 backdrop-blur-sm rounded-2xl shadow-2xl p-8 sm:p-12 text-center">
        <div className="inline-flex items-center justify-center w-20 h-20 rounded-full bg-[#D4A017]/15 border border-[#D4A017]/40 mb-6">
          <svg
            xmlns="http://www.w3.org/2000/svg"
            viewBox="0 0 24 24"
            fill="none"
            stroke="#D4A017"
            strokeWidth="1.8"
            strokeLinecap="round"
            strokeLinejoin="round"
            className="w-10 h-10"
          >
            <path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z" />
          </svg>
        </div>

        <div className="text-xs uppercase tracking-[0.3em] text-[#D4A017] mb-3 font-semibold">
          {brand}
        </div>
        <h1 className="text-3xl sm:text-4xl font-serif text-white mb-4">{title}</h1>
        <p className="text-gray-300 leading-relaxed whitespace-pre-line text-base sm:text-lg mb-8">
          {message}
        </p>

        {(supportEmail || supportPhone) && (
          <div className="border-t border-white/10 pt-6 text-sm text-gray-400">
            <p className="mb-2">Need urgent help?</p>
            <div className="flex flex-col sm:flex-row gap-2 sm:gap-4 items-center justify-center">
              {supportEmail && (
                <a
                  href={`mailto:${supportEmail}`}
                  className="text-[#D4A017] hover:underline"
                >
                  {supportEmail}
                </a>
              )}
              {supportPhone && (
                <a
                  href={`tel:${supportPhone}`}
                  className="text-[#D4A017] hover:underline"
                >
                  {supportPhone}
                </a>
              )}
            </div>
          </div>
        )}
      </div>
    </div>
  );
}
