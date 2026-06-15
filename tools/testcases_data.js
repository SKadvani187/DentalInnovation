// Test-case dataset for the DentInno admin-panel security & bug fixes.
// Consumed by make_testcases_xlsx.js. Each TEST row = 11 columns:
// [TC ID, Issue ID, Severity, Module/Page, Category, Scenario, Preconditions, Steps, Test Data, Expected, Status]

const TEST_ROWS = [];
let n = 0;
const id = () => 'TC-' + String(++n).padStart(3, '0');
function tc(issue, sev, mod, cat, scen, pre, steps, data, exp) {
  TEST_ROWS.push([id(), issue, sev, mod, cat, scen, pre, Array.isArray(steps) ? steps.join('\n') : steps, data, exp, '']);
}

/* ---------- 1) PERMISSION GATES (systemic authorization fix) ---------- */
// allowed = roles that SHOULD succeed; denied = roles that MUST be blocked (403).
const gates = [
  { iss: 'ISS-01', page: 'products.php',     perm: 'manage_products',   act: 'create / edit / delete / toggle product, CSV import, image & catalogue upload', allowed: ['super_admin', 'admin'], denied: ['staff'] },
  { iss: 'ISS-02', page: 'categories.php',   perm: 'manage_categories', act: 'save / toggle / bulk / delete category, image upload', allowed: ['super_admin', 'admin'], denied: ['staff'] },
  { iss: 'ISS-03', page: 'customers.php',    perm: 'manage_customers',  act: 'save / delete customer, CSV import', allowed: ['super_admin', 'admin'], denied: ['staff'] },
  { iss: 'ISS-04', page: 'coupons.php',      perm: 'manage_coupons',    act: 'save / generate / toggle / bulk / delete coupon', allowed: ['super_admin', 'admin'], denied: ['staff'] },
  { iss: 'ISS-05', page: 'combos.php',       perm: 'manage_combos',     act: 'save / delete combo + image upload', allowed: ['super_admin', 'admin'], denied: ['staff'] },
  { iss: 'ISS-06', page: 'offers.php',       perm: 'manage_offers',     act: 'save / delete / toggle offer + image upload', allowed: ['super_admin', 'admin'], denied: ['staff'] },
  { iss: 'ISS-15', page: 'shipping.php',     perm: 'manage_shipping',   act: 'save method / zone / rule / pincode', allowed: ['super_admin', 'admin'], denied: ['staff'] },
  { iss: 'ISS-16', page: 'settings.php',     perm: 'manage_settings',   act: 'save_setting (storefront config / pricing / secrets) + banner upload', allowed: ['super_admin'], denied: ['admin', 'staff'] },
  { iss: 'ISS-07', page: 'orders.php',       perm: 'manage_orders',     act: 'update status / payment / tracking / notes', allowed: ['super_admin', 'admin', 'staff'], denied: [] },
  { iss: 'ISS-08', page: 'reviews.php',      perm: 'manage_reviews',    act: 'approve / verify / delete review', allowed: ['super_admin', 'admin', 'staff'], denied: [] },
  { iss: 'ISS-09', page: 'questions.php',    perm: 'manage_reviews',    act: 'answer / approve / delete Q&A', allowed: ['super_admin', 'admin', 'staff'], denied: [] },
  { iss: 'ISS-10', page: 'testimonials.php', perm: 'manage_content',    act: 'save / delete testimonial + image upload', allowed: ['super_admin', 'admin', 'staff'], denied: [] },
  { iss: 'ISS-11', page: 'messages.php',     perm: 'manage_content',    act: 'mark read / delete contact message', allowed: ['super_admin', 'admin', 'staff'], denied: [] },
  { iss: 'ISS-12', page: 'bulk_quotes.php',  perm: 'manage_content',    act: 'set status / delete bulk quote', allowed: ['super_admin', 'admin', 'staff'], denied: [] },
  { iss: 'ISS-13', page: 'events.php',       perm: 'manage_content',    act: 'save / delete / change_status / mark_attended event', allowed: ['super_admin', 'admin', 'staff'], denied: [] },
  { iss: 'ISS-14', page: 'courses.php',      perm: 'manage_content',    act: 'save / delete / toggle / module ops', allowed: ['super_admin', 'admin', 'staff'], denied: [] },
];
for (const g of gates) {
  for (const role of g.allowed) {
    tc(g.iss, 'High', g.page, 'Security - Authorization',
      `Authorized role "${role}" can perform write actions (${g.perm})`,
      `Logged in as ${role}; valid CSRF token present in session`,
      ['1. Open ' + g.page + ' as ' + role,
       '2. Trigger a write action: ' + g.act,
       '3. Observe the JSON/HTTP response and the DB row'],
      `role=${role}, action=any write, valid X-CSRF / csrf_token`,
      'HTTP 200, {success:true}; the change is persisted in the DB. No 403.');
  }
  for (const role of g.denied) {
    tc(g.iss, 'High', g.page, 'Security - Authorization',
      `Under-privileged role "${role}" is BLOCKED from write actions (${g.perm})`,
      `Logged in as ${role} (role lacks ${g.perm}); valid CSRF token`,
      ['1. Log in as ' + role,
       '2. POST a write action to ' + g.page + ' with a valid CSRF token (e.g. via devtools/curl, X-Requested-With: XMLHttpRequest)',
       '3. Inspect HTTP status, JSON body, and confirm the DB is unchanged'],
      `role=${role}, action=${g.act}, valid CSRF`,
      'HTTP 403, {success:false,"...do not have permission..."}; NO database change occurs.');
  }
  // CSRF regression (must still be enforced after the permission change)
  tc(g.iss, 'High', g.page, 'Security - CSRF (regression)',
    `CSRF still enforced on ${g.page} after adding the permission gate`,
    `Logged in as an authorized role`,
    ['1. POST a write action WITHOUT a csrf_token / X-CSRF-Token header',
     '2. Observe response'],
    'No CSRF token supplied',
    'HTTP 403, {"...Invalid CSRF token..."}; request rejected before any DB write.');
}

/* ---------- 2) reports.php page-level gate (GET) ---------- */
tc('ISS-17', 'High', 'reports.php', 'Security - Authorization',
  'staff is denied the Analytics/Reports page (view_reports)',
  'Logged in as staff (no view_reports permission)',
  ['1. Navigate to pages/reports.php as staff',
   '2. Observe the rendered page / HTTP status'],
  'role=staff, GET reports.php',
  'HTTP 403 "Access denied" page; NO revenue/customer/financial data is rendered.');
tc('ISS-17', 'High', 'reports.php', 'Security - Authorization',
  'admin and super_admin can view Reports',
  'Logged in as admin (then repeat as super_admin)',
  ['1. Navigate to pages/reports.php', '2. Confirm analytics render fully'],
  'role=admin / super_admin',
  'HTTP 200; full analytics render. No 403.');

/* ---------- 3) STORED XSS fixes ---------- */
tc('ISS-18', 'High', 'questions.php', 'Security - XSS',
  'Stored XSS via malicious Q&A text in the answer button onclick',
  'A storefront question exists whose text/asker_name contains a single quote and script payload',
  ['1. From storefront, submit a product question with asker_name = ' + "O'Brien" + ' and question = ' + `'><img src=x onerror=alert(1)>`,
   '2. In admin, open questions.php',
   '3. Inspect the rendered "Answer" button HTML and load the page'],
  `question = '><img src=x onerror=alert(1)> ; asker_name = O'Brien`,
  'The JSON is emitted via htmlspecialchars(json_encode(...),ENT_QUOTES); the attribute does not break out; no script executes; the Answer modal still opens with correct data.');
tc('ISS-19', 'Medium', 'testimonials.php', 'Security - XSS',
  'Stored XSS via testimonial fields in edit button onclick',
  'A testimonial whose name/text contains a single quote / script payload',
  ['1. Save a testimonial with text = ' + `'><script>alert(1)</script>`,
   '2. Reload testimonials.php and inspect the edit (pen) button',
   '3. Click edit to confirm the modal still populates'],
  `text = '><script>alert(1)</script>`,
  'Attribute is safely encoded (ENT_QUOTES + JSON_HEX flags); no breakout/execution; edit modal opens correctly.');
tc('ISS-20', 'High', 'courses.php', 'Security - XSS',
  'Stored XSS in the course enrollments modal (student name/email)',
  'A course enrollment whose student_name contains an HTML/script payload (from storefront enrollment)',
  ['1. Create an enrollment with student_name = <img src=x onerror=alert(1)>',
   '2. In admin, open courses.php and click "View enrollments" for that course',
   '3. Observe the rendered list'],
  'student_name = <img src=x onerror=alert(1)>',
  'Name/email are escaped via escapeHtml(); payload shows as literal text; no script executes.');
tc('ISS-21', 'Low', 'events.php', 'Security - XSS',
  'Event registrations modal escapes payment_status',
  'An event registration row exists',
  ['1. Open events.php, click "View registrations"',
   '2. Inspect the payment_status badge rendering'],
  'any registration',
  'payment_status rendered through escapeHtml(); no raw HTML injection.');

/* ---------- 4) FILE UPLOAD hardening ---------- */
tc('ISS-22', 'High', 'testimonials.php', 'Security - Upload',
  'Reject a PHP script renamed as .jpg (testimonial image)',
  'Logged in with manage_content',
  ['1. Prepare evil.jpg whose bytes are <?php ... (PHP code)',
   '2. Upload it via the testimonial image picker',
   '3. Observe response and that no file is written'],
  'evil.jpg containing PHP bytes',
  'Rejected: "not a valid image (content check failed)". finfo MIME check (now strict) blocks it; nothing saved.');
tc('ISS-22', 'Medium', 'testimonials.php', 'Functional - Upload',
  'Valid image uploads succeed; oversize/upload errors reported',
  'Logged in with manage_content',
  ['1. Upload a valid 1MB PNG -> success',
   '2. Upload a 6MB image -> size error',
   '3. Trigger an upload that exceeds php upload_max_filesize'],
  'good.png (1MB); big.png (6MB)',
  'Step1 success+URL; Step2 "File too large"; Step3 clear UPLOAD_ERR message (not a misleading "Upload failed").');
tc('ISS-23', 'High', 'settings.php', 'Security - Upload',
  'Banner upload now content-validated AND super-admin gated',
  'Login as super_admin (and separately as admin to test gate)',
  ['1. As super_admin upload evil.png (PHP bytes) -> rejected by MIME check',
   '2. As super_admin upload valid banner -> success',
   '3. As admin attempt banner upload -> 403'],
  'evil.png; banner.jpg; role=admin',
  'Step1 content-check failure; Step2 success; Step3 403 (manage_settings is super-admin only).');
tc('ISS-24', 'Medium', 'products.php / categories.php / combos.php / offers.php', 'Security - Upload',
  'MIME check no longer silently skipped when finfo unavailable',
  'Server with ext/fileinfo present (normal)',
  ['1. Upload a non-image renamed to .png on each page',
   '2. Confirm rejection (previously the check was bypassed if finfo missing)'],
  'fake.png (non-image bytes)',
  'All four pages reject with "content check failed"; only genuine images pass.');

/* ---------- 5) REFUNDS money-safety ---------- */
tc('ISS-25', 'High', 'refunds.php', 'Security - Money / Negative',
  'COD / non-gateway refund amount is validated (0 < amount <= order total)',
  'A COD order with a pending refund_request whose refund_amount > order total (or <= 0)',
  ['1. Seed a refund_request for a COD order with refund_amount = total + 500',
   '2. As super_admin click Approve & Refund',
   '3. Repeat with refund_amount = 0 and a negative value'],
  'COD order total = 1000; refund_amount = 1500 / 0 / -100',
  'All rejected: "Refund amount must be greater than zero" or "Refund exceeds the order total". No status change, no payout.');
tc('ISS-26', 'High', 'refunds.php', 'Security - Money',
  'Cumulative refunds across multiple requests cannot exceed order total',
  'Two separate refund_requests on the SAME order, each 60% of total',
  ['1. Order total = 1000; request A = 600, request B = 600',
   '2. Approve request A (succeeds, 600 refunded)',
   '3. Approve request B'],
  'order=1000; A=600 (approved); B=600',
  'Request B is rejected: "Refund exceeds the order total. Already refunded ₹600 of ₹1000...". No second over-refund payout.');
tc('ISS-26', 'Medium', 'refunds.php', 'Functional - Money / Boundary',
  'Partial refunds that together equal the total are allowed',
  'Two refund_requests of 400 + 600 on a 1000 order',
  ['1. Approve A=400 (success)', '2. Approve B=600 (success)', '3. Attempt a third refund of any amount'],
  'order=1000; A=400; B=600; C=1',
  'A and B succeed (sum = total); C rejected as exceeding the total.');
tc('ISS-27', 'High', 'refunds.php', 'Functional - Recovery',
  'Gateway-succeeded / DB-failed refund is recoverable, not money-lost',
  'Simulate a DB failure during the post-gateway transaction (online order)',
  ['1. Approve an online refund; force the order UPDATE to fail (e.g. lock/disconnect mid-txn)',
   '2. Observe the request is left status=processing WITH razorpay_refund_id set, and a clear message',
   '3. On refunds list, click "Finish refund" for that row'],
  'online order; induced DB error after gateway success',
  'Step1 message: gateway refund succeeded but save failed (no 2nd refund). Step3 re-runs ONLY the DB side; order becomes refunded; NO second gateway call.');
tc('ISS-28', 'Medium', 'refunds.php', 'Negative',
  'Cannot reject a refund once it is processing/completed',
  'A refund_request in status=processing (gateway already done)',
  ['1. Attempt the "reject" action on a processing/completed request'],
  'status=processing',
  'Rejected: "Cannot reject — refund already in progress or paid out".');
tc('ISS-27', 'High', 'refunds.php', 'Security - Concurrency',
  'Double-click / concurrent approve issues at most one gateway refund',
  'An online refund_request, pending',
  ['1. Fire two concurrent Approve requests for the same id',
   '2. Inspect Razorpay refund count and request status'],
  'two simultaneous approve POSTs',
  'Exactly one gateway refund; the second gets "already being processed or completed". (Atomic claim guard.)');

/* ---------- 6) COURSES ---------- */
tc('ISS-29', 'Medium', 'courses.php', 'Functional',
  'total_lessons entered in the form is now persisted',
  'manage_content role',
  ['1. Create/edit a course, set Lessons = 12, save',
   '2. Reload and inspect the card "X lessons" and the DB row'],
  'total_lessons = 12',
  'DB courses.total_lessons = 12; card shows "12 lessons" (previously silently dropped to 0/NULL).');
tc('ISS-30', 'Medium', 'courses.php', 'Functional / Negative',
  'toggle_status uses the DB status, not a client-claimed value',
  'A published course',
  ['1. Send toggle_status with a spoofed current="draft" while DB is "published"',
   '2. Observe resulting status'],
  'spoofed current=draft; DB=published',
  'Server reads DB (published) and flips to draft correctly; a non-existent id returns "Course not found".');

/* ---------- 7) EVENTS ---------- */
tc('ISS-31', 'Medium', 'events.php', 'Negative',
  'change_status rejects values outside the allowlist',
  'An event exists',
  ['1. POST change_status with status="hacked"',
   '2. POST change_status with status="published"'],
  'status=hacked / published',
  'Invalid value -> {success:false,"Invalid status"} and no change; valid value succeeds.');
tc('ISS-32', 'Medium', 'events.php', 'Negative / Boundary',
  'Event save validates title, type, status, dates, email',
  'manage_content role',
  ['1. Save event with blank title -> error',
   '2. Save with event_type="xyz" -> coerced to "other"',
   '3. Save with blank start_date -> error',
   '4. Save with invalid contact_email -> error'],
  'title="" / type=xyz / start_date="" / email=foo',
  'Steps 1,3,4 rejected with specific messages; step2 stored as "other"; no blank-badge corruption in the grid.');
tc('ISS-33', 'Medium', 'events.php', 'Regression',
  'Events grid no longer 500s on NULL/garbage dates',
  'An event row with NULL or invalid start_date/end_date (via API/import)',
  ['1. Insert an event with start_date=NULL',
   '2. Load events.php'],
  'start_date = NULL',
  'Page renders (DateTime guarded with try/catch); no fatal error/500.');

/* ---------- 8) REPORTS ---------- */
tc('ISS-34', 'Low', 'reports.php', 'Functional - UI',
  'Order-status badges render styled (no badge-badge- double prefix)',
  'view_reports role; some orders exist',
  ['1. Open reports.php "Orders by Status"', '2. Inspect each badge class in DOM'],
  'any orders',
  'class="badge badge-warning" (single prefix) -> badges are coloured, not unstyled.');
tc('ISS-35', 'Low', 'reports.php', 'Functional - Calc',
  'Average order value uses paid-revenue / paid-orders (same basis)',
  'Mixed paid and unpaid orders in the range',
  ['1. Set a date range with both paid and unpaid orders',
   '2. Read the "Avg order value" KPI and verify the math'],
  'paid revenue=10000 over 5 paid orders; 3 unpaid orders',
  'AOV = 10000/5 = 2000 (not 10000/8). Both numerator & denominator are paid-basis.');
tc('ISS-36', 'Low', 'reports.php', 'Security - XSS',
  'SKU / category / payment_method / clinic_name escaped in tables',
  'A product SKU or clinic_name containing <b> or a quote',
  ['1. Set a product SKU = <b>x</b>; a customer clinic_name with markup',
   '2. Open reports.php top-products / top-customers / payment-methods'],
  'sku=<b>x</b>; clinic_name with HTML',
  'Values render as literal text (htmlspecialchars); no markup injection.');

/* ---------- 9) SHIPPING ---------- */
tc('ISS-37', 'High', 'shipping.php', 'Negative - Money',
  'Negative base_cost on a shipping method is rejected',
  'manage_shipping role',
  ['1. Save a method with base_cost = -100', '2. Save with base_cost = 0 and a valid positive value'],
  'base_cost = -100 / 0 / 50',
  'Negative rejected ("Base cost must be 0 or more"); 0 and positive accepted. Checkout cannot be reduced by a negative rate.');
tc('ISS-38', 'High', 'shipping.php', 'Negative - Money / Boundary',
  'Shipping rule validates cost>=0 and min<=max',
  'manage_shipping role',
  ['1. Save a rule with cost = -50 -> error',
   '2. Save a rule with min_value=100, max_value=50 -> error',
   '3. Save a free rule (is_free=1) -> cost forced to 0',
   '4. Save a valid rule min=0,max=500,cost=80'],
  'cost=-50; min=100/max=50; is_free=1; min=0/max=500/cost=80',
  'Steps1-2 rejected with specific messages; step3 stored cost=0; step4 saved.');
tc('ISS-39', 'Medium', 'shipping_calculator.php', 'Functional - Calc',
  'Calculator breakdown matches the authoritative checkout engine',
  'Configure a zone-specific rule that differs from a global rule for the same method',
  ['1. Create a global rule and a zone-specific rule for method M',
   '2. In the calculator pick that zone, enter qty/price/weight',
   '3. Compare each method row AND the "Actual charge" to a real cart at checkout'],
  'global vs zone-specific rule; pick zone',
  'Per-method rows now use methodShippingCost() — zone-specific beats global; NULL max_value handled; values equal what checkout charges.');
tc('ISS-40', 'Low', 'shipping.php / shipping_calculator.php', 'Security - XSS',
  'Method name/type/description escaped in calculator output',
  'A shipping method whose name contains markup',
  ['1. Name a method <i>x</i>', '2. Run the calculator and inspect rendered rows'],
  'method name=<i>x</i>',
  'Rendered via escapeHtml(); shown as literal text.');

/* ---------- 10) OFFERS ---------- */
tc('ISS-41', 'Medium', 'offers.php', 'Functional - Boundary',
  'Same-day "Expired" badge matches the SQL expired filter',
  'An offer whose valid_till is today 23:59:59',
  ['1. Create an offer expiring today',
   '2. View the grid card badge',
   '3. Cross-check against the "Expired" status filter'],
  'valid_till = today 23:59:59',
  'Card badge and SQL filter agree (datetime compared via strtotime vs time()); not flagged expired until actually past.');
tc('ISS-06b', 'High', 'offers.php', 'Functional - Money',
  'Offer pricing recomputed server-side (regression)',
  'manage_offers role',
  ['1. Submit an offer with a tampered total_mrp/you_save', '2. Inspect stored values'],
  'client total_mrp falsified',
  'Server recomputes mrp/you_save from live products; client values ignored (unchanged by this work, verify still holds).');

/* ---------- 11) COMBOS ---------- */
tc('ISS-42', 'Medium', 'combos.php', 'Security - Info leak',
  'DB error details not leaked to the client in production',
  'APP_DEBUG = false (production)',
  ['1. Force a DB error in a combo save (e.g. invalid data)',
   '2. Inspect the JSON message returned',
   '3. Repeat with APP_DEBUG=true (dev)'],
  'induced DB error; APP_DEBUG false then true',
  'Prod: generic "Server error. Please try again." (detail only in server log). Dev: detailed message. No SQL/column leakage in prod.');

/* ---------- 12) COUPONS ---------- */
tc('ISS-43', 'Medium', 'coupons.php', 'Negative - Money / Boundary',
  'Bulk-generate clamps min_order/max_discount/uses_limit',
  'manage_coupons role',
  ['1. Generate with min_order = -50 -> clamped to 0',
   '2. Generate with max_discount = -10 -> stored NULL',
   '3. Generate with uses_limit = 0 -> stored NULL (unlimited, not unusable)',
   '4. Generate percent value = 150 -> rejected'],
  'min_order=-50; max_discount=-10; uses_limit=0; value=150%',
  'No negative money stored; 0 uses_limit treated as unlimited; >100% rejected.');
tc('ISS-44', 'Low', 'coupons.php', 'Functional - UI',
  'toggleCoupon reports real server outcome (no false success)',
  'manage_coupons role',
  ['1. Toggle a coupon (success path) -> toast shows server message + reload',
   '2. Simulate a 403 (e.g. expired CSRF) -> error toast, no reload'],
  'valid toggle; then forced 403',
  'Success path reloads with success toast; failure path shows error toast and does NOT falsely claim success.');

/* ---------- 13) CUSTOMERS ---------- */
tc('ISS-45', 'Medium', 'customers.php', 'Functional - Data',
  'Phone stored in canonical digits form; duplicate detection consistent',
  'manage_customers role',
  ['1. Add customer with phone "98765 43210"',
   '2. Add another with phone "9876543210"',
   '3. Inspect stored phone of the first and the dup error'],
  'phone "98765 43210" then "9876543210"',
  'First stored as "9876543210"; the second is rejected as a duplicate (same canonical number).');
tc('ISS-46', 'Low', 'customers.php', 'Negative - Boundary',
  'Oversized text fields rejected by maxLen validators',
  'manage_customers role',
  ['1. Save a customer with notes > 2000 chars / city > 80 chars'],
  'notes length 5000',
  'Rejected with a validation message; no DB truncation/error.');

/* ---------- 14) PRODUCTS ---------- */
tc('ISS-47', 'Medium', 'products.php', 'Negative - Boundary',
  'CSV import rejects rows with negative stock',
  'manage_products role',
  ['1. Import a CSV with a row stock = -5',
   '2. Check import summary and that no negative-stock product is created'],
  'CSV row stock=-5',
  'Row counted as skipped; no product with negative stock (matches the manual-save rule).');
tc('ISS-48', 'Low', 'products.php', 'Regression',
  'toggle / approve_review / delete_review cast ids to int',
  'manage_products role',
  ['1. Call toggle with id as a numeric string', '2. Call approve_review with approved as "1"/"0"'],
  'id="12"; approved="1"',
  'Actions operate on the intended row; approved coerced to 0/1; no type ambiguity.');

/* ---------- 15) ORDERS ---------- */
tc('ISS-49', 'Medium', 'orders.php', 'Security - XSS',
  'Tracking number / courier / order_number escaped in the order view',
  'manage_orders role',
  ['1. Set tracking_number = "><script>alert(1)</script> via update_tracking',
   '2. Open the order detail view and inspect the tracking/courier inputs and order number'],
  'tracking_number = "><script>...',
  'Values are htmlspecialchars-escaped in attributes/text; no attribute breakout or script execution.');

/* ---------- 16) ADMINS ---------- */
tc('ISS-50', 'Medium', 'admins.php', 'Negative - Authorization',
  'Super admin cannot change own role or deactivate self',
  'Logged in as super_admin (with another super_admin also present)',
  ['1. Edit your OWN account and change role to admin -> blocked',
   '2. Edit your OWN account and set is_active=0 -> blocked',
   '3. Have a SECOND super_admin change the first one (allowed)'],
  'self id == session admin_id; role change / deactivate',
  'Steps1-2 rejected ("cannot change your own role/deactivate"); step3 allowed. Last-super-admin guard still applies.');
tc('ISS-50b', 'High', 'admins.php', 'Security - Authorization (regression)',
  'Only super_admin can reach admin-user management',
  'Login as admin and staff',
  ['1. As admin/staff POST a save/delete to admins.php with valid CSRF'],
  'role=admin / staff',
  'HTTP 403 — manage_admins is super-admin only (unchanged; verify still holds).');
tc('ISS-50c', 'High', 'admins.php', 'Negative - Boundary (regression)',
  'Last active super admin cannot be demoted/deactivated/deleted',
  'Exactly one active super_admin',
  ['1. Try to demote / deactivate / delete the only super_admin'],
  'single super_admin',
  'Blocked: "Cannot demote/deactivate/delete the last active super admin".');

/* ---------- 17) SETTINGS misc ---------- */
tc('ISS-51', 'Low', 'settings.php', 'Functional - Data',
  'Setting key regex preserves digits and underscores',
  'super_admin',
  ['1. Save a setting with key containing a digit/underscore (e.g. gvp_threshold)',
   '2. Verify the row key is not mangled'],
  'key=gvp_threshold',
  'Stored under the exact key (regex now allows a-zA-Z0-9_); no silent corruption to "gvpthreshold".');
tc('ISS-52', 'Low', 'settings.php', 'Functional',
  'Home "add category section" dropdown lists categories even with zero products',
  'A catalog with active categories but no active products',
  ['1. Open settings.php?page=home', '2. Open the category-section dropdown'],
  'categories>0, products=0',
  'Categories load (guard no longer keyed on $linkProducts being non-empty).');
tc('ISS-53', 'Low', 'settings.php', 'Security - XSS',
  'Current admin email escaped in the profile card',
  'Any logged-in admin',
  ['1. Open settings.php (account tab)', '2. Inspect the email line in the avatar card'],
  'email with markup (hypothetical)',
  'Email rendered with htmlspecialchars; consistent with name/role rendering.');

/* ---------- 18) Cross-cutting regression ---------- */
tc('ALL', 'High', 'includes/auth.php', 'Regression - RBAC',
  'New manage_shipping permission wired to admin + super_admin only',
  'Roles configured per rolePermissions()',
  ['1. Verify hasPermission("manage_shipping") is true for super_admin and admin, false for staff'],
  'each role',
  'super_admin/admin = true; staff = false. Matches shipping.php gate.');
tc('ALL', 'Medium', 'all admin pages', 'Regression - Smoke',
  'All modified pages still load and basic CRUD works for authorized roles',
  'super_admin login',
  ['1. Visit every page in the sidebar',
   '2. Perform one create + one edit + one delete per module',
   '3. Confirm no PHP fatal/JSON-parse errors'],
  'super_admin full pass',
  'All pages render; all CRUD succeeds; no 500s / no "Unexpected token <" JSON errors.');

/* ====================== ISSUES SUMMARY ====================== */
const ISSUE_ROWS = [
  ['ISS-01', 'High', 'Authorization', 'pages/products.php', 'Write handlers (CRUD, CSV, uploads) checked CSRF but not role — staff could manage products.', 'Added requirePermissionAjax(\'manage_products\') after the CSRF check.'],
  ['ISS-02', 'High', 'Authorization', 'pages/categories.php', 'No permission gate on save/toggle/bulk/delete/upload.', 'Added requirePermissionAjax(\'manage_categories\').'],
  ['ISS-03', 'High', 'Authorization', 'pages/customers.php', 'No permission gate on save / CSV import.', 'Added requirePermissionAjax(\'manage_customers\').'],
  ['ISS-04', 'High', 'Authorization', 'pages/coupons.php', 'No gate; staff could bulk-generate up to 500 codes.', 'Added requirePermissionAjax(\'manage_coupons\').'],
  ['ISS-05', 'High', 'Authorization', 'pages/combos.php', 'No gate on upload + JSON handlers.', 'Added requirePermissionAjax(\'manage_combos\') to both handlers.'],
  ['ISS-06', 'High', 'Authorization', 'pages/offers.php', 'No gate on upload + JSON handlers.', 'Added requirePermissionAjax(\'manage_offers\') to both handlers.'],
  ['ISS-07', 'High', 'Authorization', 'pages/orders.php', 'No role check on status/payment/tracking writes.', 'Added requirePermissionAjax(\'manage_orders\').'],
  ['ISS-08', 'High', 'Authorization', 'pages/reviews.php', 'No gate on approve/verify/delete.', 'Added requirePermissionAjax(\'manage_reviews\').'],
  ['ISS-09', 'High', 'Authorization', 'pages/questions.php', 'No gate on answer/approve/delete.', 'Added requirePermissionAjax(\'manage_reviews\').'],
  ['ISS-10', 'High', 'Authorization', 'pages/testimonials.php', 'No gate on upload + save/delete.', 'Added requirePermissionAjax(\'manage_content\') to both handlers.'],
  ['ISS-11', 'High', 'Authorization', 'pages/messages.php', 'No gate on read/delete.', 'Added requirePermissionAjax(\'manage_content\').'],
  ['ISS-12', 'High', 'Authorization', 'pages/bulk_quotes.php', 'No gate on status/delete.', 'Added requirePermissionAjax(\'manage_content\').'],
  ['ISS-13', 'High', 'Authorization', 'pages/events.php', 'No gate; PII (registrations) readable by any admin.', 'Added requirePermissionAjax(\'manage_content\').'],
  ['ISS-14', 'High', 'Authorization', 'pages/courses.php', 'No gate; enrollment PII readable by any admin.', 'Added requirePermissionAjax(\'manage_content\').'],
  ['ISS-15', 'High', 'Authorization', 'pages/shipping.php + includes/auth.php', 'Shipping writes (live checkout rates) had no permission at all.', 'Added a new manage_shipping permission (admin+super) and gated all shipping writes.'],
  ['ISS-16', 'High', 'Authorization', 'pages/settings.php', 'Only 3 secret keys were super-admin gated; all other storefront/pricing settings + banner upload were open to admin/staff.', 'Blanket requirePermissionAjax(\'manage_settings\') on save_setting and banner upload (super-admin only).'],
  ['ISS-17', 'High', 'Authorization', 'pages/reports.php', 'Financial analytics page had no view_reports check; staff could view revenue.', 'Added requirePermissionPage(\'view_reports\') + new HTML 403 helper.'],
  ['ISS-18', 'High', 'Stored XSS', 'pages/questions.php', 'json_encode($q) injected raw into a single-quoted onclick — Q&A text could break out.', 'Wrapped in htmlspecialchars(json_encode(...,JSON_HEX_*),ENT_QUOTES).'],
  ['ISS-19', 'Medium', 'Stored XSS', 'pages/testimonials.php', 'Same raw json_encode-in-attribute pattern.', 'Same ENT_QUOTES + JSON_HEX hardening.'],
  ['ISS-20', 'High', 'Stored XSS', 'pages/courses.php', 'Enrollment modal injected student_name/email raw into innerHTML.', 'Wrapped values in escapeHtml().'],
  ['ISS-21', 'Low', 'Stored XSS', 'pages/events.php', 'Registration payment_status rendered raw.', 'Wrapped in escapeHtml().'],
  ['ISS-22', 'High', 'File upload', 'pages/testimonials.php', 'Extension-only check; no MIME/UPLOAD_ERR handling.', 'Added finfo MIME content check (strict) + UPLOAD_ERR handling.'],
  ['ISS-23', 'High', 'File upload', 'pages/settings.php', 'Banner upload was extension-only and ungated.', 'Added strict MIME check, UPLOAD_ERR handling, and manage_settings gate.'],
  ['ISS-24', 'Medium', 'File upload', 'products/categories/combos/offers', 'finfo MIME check silently skipped if finfo unavailable (if ($mime && ...)).', 'Changed to (!$mime || ...) so a missing/blank MIME fails closed.'],
  ['ISS-25', 'High', 'Money', 'pages/refunds.php', 'COD/non-gateway refunds skipped all amount validation.', 'Validate 0 < amount <= order total for every method.'],
  ['ISS-26', 'High', 'Money', 'pages/refunds.php', 'Per-request double-refund guard only; multiple requests could exceed 100%.', 'Added cumulative cap: amount + already-completed refunds <= order total.'],
  ['ISS-27', 'High', 'Reliability', 'pages/refunds.php', 'Gateway-success then DB-fail left request stuck in un-retryable processing (money out, order not refunded).', 'Recovery path: detect processing+refund_id, allow "Finish refund" to re-run DB only (no 2nd gateway call); clearer messaging + server logging.'],
  ['ISS-28', 'Medium', 'Money', 'pages/refunds.php', 'A processing/paid-out request could still be rejected.', 'Reject now restricted to pending/approved.'],
  ['ISS-29', 'Medium', 'Functional', 'pages/courses.php', 'total_lessons collected in the form but never written to DB.', 'Added total_lessons to INSERT/UPDATE.'],
  ['ISS-30', 'Medium', 'Functional', 'pages/courses.php', 'toggle_status trusted client-supplied current status.', 'Read current status from DB; 404 if missing.'],
  ['ISS-31', 'Medium', 'Validation', 'pages/events.php', 'change_status accepted any string.', 'Validated against status allowlist.'],
  ['ISS-32', 'Medium', 'Validation', 'pages/events.php', 'Event save did not validate title/type/status/dates/email.', 'Added server-side validation + type/status allowlist coercion.'],
  ['ISS-33', 'Medium', 'Reliability', 'pages/events.php', 'new DateTime() on NULL/garbage dates 500-ed the page.', 'Guarded with try/catch + status badge fallback.'],
  ['ISS-34', 'Low', 'UI bug', 'pages/reports.php', 'badge-<?=statusBadge()?> produced badge-badge-* (unstyled).', 'Use class="badge <?=statusBadge()?>".'],
  ['ISS-35', 'Low', 'Calc', 'pages/reports.php', 'AOV = paid revenue / ALL orders (understated).', 'AOV = paid revenue / paid orders.'],
  ['ISS-36', 'Low', 'XSS', 'pages/reports.php', 'sku/category/payment_method/clinic_name echoed unescaped.', 'Wrapped in htmlspecialchars().'],
  ['ISS-37', 'High', 'Money', 'pages/shipping.php', 'Negative base_cost accepted (could reduce checkout total).', 'Reject base_cost < 0 / non-numeric.'],
  ['ISS-38', 'High', 'Money', 'pages/shipping.php', 'Rule cost could be negative; min>max silently dead.', 'Validate cost>=0, min<=max; free rule forces cost 0.'],
  ['ISS-39', 'Medium', 'Calc', 'pages/shipping_calculator.php', 'Duplicate inline engine with zone-precedence & max_value bugs could show wrong rates.', 'Replaced with the authoritative methodShippingCost()/computeShipping().'],
  ['ISS-40', 'Low', 'XSS', 'shipping.php / shipping_calculator.php', 'Method name/type/description rendered raw in client innerHTML.', 'Wrapped in escapeHtml().'],
  ['ISS-41', 'Medium', 'Logic', 'pages/offers.php', 'Expired badge compared datetime to bare date — disagreed with SQL filter.', 'Compare via strtotime() vs time().'],
  ['ISS-42', 'Medium', 'Info leak', 'pages/combos.php', 'Catch returned raw $e->getMessage() to the client.', 'Log server-side; expose detail only when APP_DEBUG.'],
  ['ISS-43', 'Medium', 'Money', 'pages/coupons.php', 'generate bypassed Validator: negative min_order/max_discount; uses_limit=0 -> unlimited.', 'Clamp min_order>=0, max_discount>0-or-null, uses_limit>0-or-null.'],
  ['ISS-44', 'Low', 'UI', 'pages/coupons.php', 'toggleCoupon ignored server response (masked 403s).', 'Parse response; toast real outcome; reload only on success.'],
  ['ISS-45', 'Medium', 'Data', 'pages/customers.php', 'Phone stored unnormalized but dupe-checked normalized.', 'Normalize to digits once; store + dupe-check the canonical value.'],
  ['ISS-46', 'Low', 'Validation', 'pages/customers.php', 'No length caps on city/state/address/clinic/notes.', 'Added maxLen validators.'],
  ['ISS-47', 'Medium', 'Validation', 'pages/products.php', 'CSV import accepted negative stock.', 'Reject (int)stock < 0 in the importer.'],
  ['ISS-48', 'Low', 'Robustness', 'pages/products.php', 'toggle/approve_review/delete_review used uncast ids.', 'Cast ids to int; approved coerced to 0/1.'],
  ['ISS-49', 'Medium', 'XSS', 'pages/orders.php', 'order_number / tracking_number / courier_name echoed raw.', 'Wrapped in htmlspecialchars().'],
  ['ISS-50', 'Medium', 'Authorization', 'pages/admins.php', 'Super admin could demote/deactivate self (lose access).', 'Block self role-change / self-deactivate (mirrors self-delete guard).'],
  ['ISS-51', 'Low', 'Data', 'pages/settings.php', 'Key regex stripped digits/underscores, mangling some keys.', 'Allow [a-zA-Z0-9_].'],
  ['ISS-52', 'Low', 'Logic', 'pages/settings.php', 'Category dropdown only queried when products existed.', 'Always query categories.'],
  ['ISS-53', 'Low', 'XSS', 'pages/settings.php', 'Current admin email echoed unescaped.', 'Wrapped in htmlspecialchars().'],
];

/* ====================== ROLES REFERENCE ====================== */
const ROLE_ROWS = [
  ['super_admin', 'ALL permissions implicitly, including manage_refunds, manage_admins, manage_settings.', 'Full access. Cannot demote/deactivate/delete the last active super admin; cannot change own role/active state.'],
  ['admin', 'manage_products, manage_categories, manage_orders, manage_coupons, manage_customers, manage_combos, manage_offers, manage_reviews, manage_content, manage_shipping, view_reports', 'NEW: manage_shipping added. Cannot touch refunds, admin users, or settings.'],
  ['staff', 'manage_orders, manage_reviews, manage_content', 'Limited: orders, reviews/Q&A, content (testimonials/messages/quotes/events/courses). NO products, categories, customers, coupons, combos, offers, shipping, reports, settings, refunds, admins.'],
  ['(any logged-in)', 'requireLogin() enforced for everything under /pages/.', 'Auth backstop in includes/auth.php; each handler also CSRF-checks and now permission-checks.'],
];

module.exports = { TEST_ROWS, ISSUE_ROWS, ROLE_ROWS };
