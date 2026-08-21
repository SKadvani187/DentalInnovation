-- The Events nav icon was 'fa-calendar-star', a Font Awesome PRO-only icon, so it rendered blank
-- in the admin sidebar (the project ships FA Free). Switch to a Free equivalent. Idempotent.
UPDATE page_registry SET icon = 'fa-calendar-days' WHERE page_key = 'events' AND icon = 'fa-calendar-star';
