// Dependency-free .xlsx generator documenting the DentInno dashboard calculations + a worked example.
// Node built-ins only (ZIP entries STORED; no zlib needed).
const fs = require('fs');
const path = require('path');

/* ---------------- ZIP (stored) + CRC32 ---------------- */
const CRC_TABLE = (() => { let c, t = []; for (let n = 0; n < 256; n++) { c = n; for (let k = 0; k < 8; k++) c = (c & 1) ? (0xEDB88320 ^ (c >>> 1)) : (c >>> 1); t[n] = c >>> 0; } return t; })();
function crc32(buf) { let crc = 0xFFFFFFFF; for (let i = 0; i < buf.length; i++) crc = (crc >>> 8) ^ CRC_TABLE[(crc ^ buf[i]) & 0xFF]; return (crc ^ 0xFFFFFFFF) >>> 0; }
function zip(files) {
  const locals = []; const central = []; let offset = 0;
  for (const f of files) {
    const name = Buffer.from(f.name, 'utf8'); const data = Buffer.from(f.data, 'utf8'); const crc = crc32(data);
    const lh = Buffer.alloc(30);
    lh.writeUInt32LE(0x04034b50, 0); lh.writeUInt16LE(20, 4); lh.writeUInt16LE(0, 6); lh.writeUInt16LE(0, 8);
    lh.writeUInt16LE(0, 10); lh.writeUInt16LE(0, 12); lh.writeUInt32LE(crc, 14); lh.writeUInt32LE(data.length, 18);
    lh.writeUInt32LE(data.length, 22); lh.writeUInt16LE(name.length, 26); lh.writeUInt16LE(0, 28);
    locals.push(lh, name, data);
    const ch = Buffer.alloc(46);
    ch.writeUInt32LE(0x02014b50, 0); ch.writeUInt16LE(20, 4); ch.writeUInt16LE(20, 6); ch.writeUInt16LE(0, 8);
    ch.writeUInt16LE(0, 10); ch.writeUInt16LE(0, 12); ch.writeUInt16LE(0, 14); ch.writeUInt32LE(crc, 16);
    ch.writeUInt32LE(data.length, 20); ch.writeUInt32LE(data.length, 24); ch.writeUInt16LE(name.length, 28);
    ch.writeUInt16LE(0, 30); ch.writeUInt16LE(0, 32); ch.writeUInt16LE(0, 34); ch.writeUInt16LE(0, 36);
    ch.writeUInt32LE(0, 38); ch.writeUInt32LE(offset, 42); central.push(ch, name);
    offset += lh.length + name.length + data.length;
  }
  const cdStart = offset; let cdSize = 0; for (const b of central) cdSize += b.length;
  const eocd = Buffer.alloc(22);
  eocd.writeUInt32LE(0x06054b50, 0); eocd.writeUInt16LE(0, 4); eocd.writeUInt16LE(0, 6);
  eocd.writeUInt16LE(files.length, 8); eocd.writeUInt16LE(files.length, 10);
  eocd.writeUInt32LE(cdSize, 12); eocd.writeUInt32LE(cdStart, 16); eocd.writeUInt16LE(0, 20);
  return Buffer.concat([...locals, ...central, eocd]);
}
/* ---------------- XLSX helpers ---------------- */
const esc = s => String(s == null ? '' : s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
function colLetter(i) { let s = ''; i++; while (i > 0) { const m = (i - 1) % 26; s = String.fromCharCode(65 + m) + s; i = Math.floor((i - 1) / 26); } return s; }
function sheetXml(rows, cols) {
  const colsXml = cols.map((w, i) => `<col min="${i + 1}" max="${i + 1}" width="${w}" customWidth="1"/>`).join('');
  let body = '';
  rows.forEach((row, r) => {
    const rn = r + 1;
    const cells = row.map((cell, c) => {
      const ref = colLetter(c) + rn;
      const isObj = cell && typeof cell === 'object';
      const v = isObj ? cell.v : cell;
      const style = (r === 0) ? 1 : (isObj && cell.h ? 4 : (isObj && cell.center ? 3 : 2));
      return `<c r="${ref}" s="${style}" t="inlineStr"><is><t xml:space="preserve">${esc(v)}</t></is></c>`;
    }).join('');
    body += `<row r="${rn}"${r === 0 ? ' ht="26" customHeight="1"' : ''}>${cells}</row>`;
  });
  const freeze = `<sheetViews><sheetView workbookViewId="0"><pane ySplit="1" topLeftCell="A2" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews>`;
  return `<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">${freeze}<cols>${colsXml}</cols><sheetData>${body}</sheetData></worksheet>`;
}
const stylesXml = `<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">
<fonts count="3"><font><sz val="10"/><name val="Calibri"/></font><font><b/><sz val="10"/><color rgb="FFFFFFFF"/><name val="Calibri"/></font><font><b/><sz val="10"/><name val="Calibri"/></font></fonts>
<fills count="3"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill><fill><patternFill patternType="solid"><fgColor rgb="FF1F2A44"/><bgColor indexed="64"/></patternFill></fill></fills>
<borders count="2"><border><left/><right/><top/><bottom/><diagonal/></border><border><left style="thin"><color rgb="FFCCCCCC"/></left><right style="thin"><color rgb="FFCCCCCC"/></right><top style="thin"><color rgb="FFCCCCCC"/></top><bottom style="thin"><color rgb="FFCCCCCC"/></bottom></border></borders>
<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>
<cellXfs count="5">
<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>
<xf numFmtId="0" fontId="1" fillId="2" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="center" wrapText="1"/></xf>
<xf numFmtId="0" fontId="0" fillId="0" borderId="1" xfId="0" applyBorder="1" applyAlignment="1"><alignment vertical="top" wrapText="1"/></xf>
<xf numFmtId="0" fontId="0" fillId="0" borderId="1" xfId="0" applyBorder="1" applyAlignment="1"><alignment horizontal="right" vertical="top" wrapText="1"/></xf>
<xf numFmtId="0" fontId="2" fillId="0" borderId="1" xfId="0" applyFont="1" applyBorder="1" applyAlignment="1"><alignment horizontal="right" vertical="top" wrapText="1"/></xf>
</cellXfs></styleSheet>`;
function buildWorkbook(sheets, outPath) {
  const ct = `<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
<Default Extension="xml" ContentType="application/xml"/>
<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>
${sheets.map((s, i) => `<Override PartName="/xl/worksheets/sheet${i + 1}.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>`).join('')}
<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/></Types>`;
  const rels = `<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>`;
  const wb = `<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets>
${sheets.map((s, i) => `<sheet name="${esc(s.name)}" sheetId="${i + 1}" r:id="rId${i + 1}"/>`).join('')}</sheets></workbook>`;
  const wbRels = `<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
${sheets.map((s, i) => `<Relationship Id="rId${i + 1}" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet${i + 1}.xml"/>`).join('')}
<Relationship Id="rId${sheets.length + 1}" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/></Relationships>`;
  const files = [
    { name: '[Content_Types].xml', data: ct }, { name: '_rels/.rels', data: rels },
    { name: 'xl/workbook.xml', data: wb }, { name: 'xl/_rels/workbook.xml.rels', data: wbRels },
    { name: 'xl/styles.xml', data: stylesXml },
    ...sheets.map((s, i) => ({ name: `xl/worksheets/sheet${i + 1}.xml`, data: sheetXml(s.rows, s.cols) })),
  ];
  fs.writeFileSync(outPath, zip(files));
}
const H = v => ({ v, h: true });   // bold-right (numbers/results)
const R = v => ({ v, center: false });

/* ===================== SHEET 1: Metrics reference ===================== */
const S1_HEAD = ['Metric', 'Where shown', 'What it measures', 'Basis', 'SQL / Formula', 'Notes'];
const S1 = [
  ['Total Revenue', 'Top card', 'Lifetime net sales', 'NET (subtotal), paid only', "SUM(subtotal) WHERE payment_status='paid'", 'Excludes tax & shipping (pass-through). Unpaid/partial/cancelled/refunded excluded.'],
  ['Total Orders', 'Top card', 'Orders placed (not cancelled)', 'Count, excl. cancelled', "COUNT(*) WHERE status <> 'cancelled'", 'Refunded orders are still counted (they were real sales).'],
  ['Total Customers', 'Top card', 'Active customers', 'Count, active only', "COUNT(*) FROM customers WHERE is_active=1", '—'],
  ['Total Products', 'Top card', 'Active products', 'Count, active only', "COUNT(*) FROM products WHERE is_active=1", 'Soft-deleted products are is_active=0, so excluded.'],
  ['Monthly Revenue', 'Top card', 'This month net sales', 'NET (subtotal), paid, current month', "SUM(subtotal) WHERE payment_status='paid' AND MONTH(created_at)=MONTH(NOW()) AND YEAR=YEAR(NOW())", 'By order date (created_at).'],
  ['Pending Orders', 'Orders card sub-label', 'Orders awaiting action', 'Count', "COUNT(*) WHERE status='pending'", '—'],
  ['Low Stock Items', 'Top card', 'Products at/under reorder level', 'Count, active', "COUNT(*) WHERE stock <= min_stock_alert AND is_active=1", 'Includes out-of-stock (stock=0).'],
  ['New Customers (this month)', 'Customers card sub-label', 'Active sign-ups this month', 'Count, active, current month', "COUNT(*) FROM customers WHERE is_active=1 AND MONTH(created_at)=MONTH(NOW()) AND YEAR=YEAR(NOW())", 'Active-only so it is a true subset of Total Customers.'],
  ['Upcoming Events', 'Engage card', 'Future published events', 'Count', "COUNT(*) FROM events WHERE status='published' AND start_date >= NOW()", '—'],
  ['Registrations', 'Engage card sub-label', 'Total event registrations', 'Count', "COUNT(*) FROM event_registrations", '—'],
  ['Published Courses', 'Engage card', 'Live courses', 'Count', "COUNT(*) FROM courses WHERE status='published'", '—'],
  ['Enrollments', 'Engage card sub-label', 'Total course enrollments', 'Count', "COUNT(*) FROM course_enrollments", '—'],
  ['Avg Rating', 'Engage card', 'Mean approved review rating', 'Average', "ROUND(AVG(rating),1) WHERE is_approved=1", "Shows '—' when there are no approved reviews."],
  ['Pending Reviews', 'Engage card sub-label', 'Reviews awaiting moderation', 'Count', "COUNT(*) FROM product_reviews WHERE is_approved=0", '—'],
  ['Shipping Methods', 'Engage card', 'Active shipping methods', 'Count', "COUNT(*) FROM shipping_methods WHERE is_active=1", '—'],
  ['Revenue chart (6 mo.)', 'Line chart', 'Monthly net sales, last 6 months', 'NET (subtotal), paid, per month', "SUM(subtotal) WHERE payment_status='paid' GROUP BY YYYY-MM; zero-filled to 6 months in PHP", "Current-month point equals the Monthly Revenue card."],
  ['Order Status Breakdown', 'Doughnut', 'Order count by status', 'Count, all statuses', "COUNT(*) GROUP BY status", 'Includes cancelled/refunded; each status has a fixed colour.'],
  ['Recent Orders', 'Table', 'Latest 8 orders', 'List', "ORDER BY created_at DESC LIMIT 8", 'Shows order #, customer, amount (total), status.'],
  ['Top Selling Products', 'Table', 'Best sellers', 'List', "ORDER BY total_sales DESC LIMIT 5 WHERE is_active=1", '—'],
];

/* ===================== SHEET 2: Sample orders ===================== */
// Tax = 18% of subtotal. Total = subtotal + shipping + tax. "Today" = 14 Jun 2026.
const S2_HEAD = ['Order #', 'Status', 'Payment', 'Subtotal', 'Shipping', 'Tax (18%)', 'Total', 'Created'];
const ORDERS = [
  ['O-001', 'delivered', 'paid', 10000, 200, 1800, 12000, '2026-06-03'],
  ['O-002', 'shipped', 'paid', 5000, 150, 900, 6050, '2026-06-10'],
  ['O-003', 'confirmed', 'paid', 20000, 0, 3600, 23600, '2026-05-15'],
  ['O-004', 'pending', 'unpaid', 8000, 200, 1440, 9640, '2026-06-12'],
  ['O-005', 'cancelled', 'unpaid', 3000, 100, 540, 3640, '2026-06-08'],
  ['O-006', 'refunded', 'refunded', 7000, 150, 1260, 8410, '2026-04-20'],
  ['O-007', 'delivered', 'partial', 4000, 100, 720, 4820, '2026-06-01'],
  ['O-008', 'processing', 'paid', 15000, 250, 2700, 17950, '2026-03-22'],
];
const S2 = ORDERS.map(o => [o[0], o[1], o[2], H(o[3].toLocaleString('en-IN')), H(o[4].toLocaleString('en-IN')), H(o[5].toLocaleString('en-IN')), H(o[6].toLocaleString('en-IN')), o[7]]);
S2.push(['TOTAL (all)', '', '', H('72,000'), H('1,150'), H('12,960'), H('86,110'), '']);

/* ===================== SHEET 3: Worked example ===================== */
const S3_HEAD = ['Metric', 'Which orders qualify', 'Calculation', 'Result'];
const S3 = [
  ['Total Revenue (net)', 'paid: O-001, O-002, O-003, O-008 (subtotal)', '10,000 + 5,000 + 20,000 + 15,000', H('₹50,000')],
  ['Total Orders', 'all 8 except cancelled O-005', '8 − 1', H('7')],
  ['Monthly Revenue (Jun)', 'paid AND created in Jun 2026: O-001, O-002', '10,000 + 5,000  (O-007 excluded: partial)', H('₹15,000')],
  ['Pending Orders', "status='pending': O-004", 'count', H('1')],
  ['Revenue chart — Jan 2026', 'no paid orders', '0', H('₹0')],
  ['Revenue chart — Feb 2026', 'no paid orders', '0', H('₹0')],
  ['Revenue chart — Mar 2026', 'paid: O-008', '15,000', H('₹15,000')],
  ['Revenue chart — Apr 2026', 'O-006 is refunded (not paid)', '0', H('₹0')],
  ['Revenue chart — May 2026', 'paid: O-003', '20,000', H('₹20,000')],
  ['Revenue chart — Jun 2026', 'paid: O-001, O-002', '10,000 + 5,000', H('₹15,000')],
  ['  ↳ reconciliation', 'Jun chart point = Monthly Revenue card', '15,000 = 15,000', H('✓ match')],
  ['Doughnut — pending', 'O-004', 'count', H('1')],
  ['Doughnut — processing', 'O-008', 'count', H('1')],
  ['Doughnut — confirmed', 'O-003', 'count', H('1')],
  ['Doughnut — shipped', 'O-002', 'count', H('1')],
  ['Doughnut — delivered', 'O-001, O-007', 'count', H('2')],
  ['Doughnut — cancelled', 'O-005', 'count', H('1')],
  ['Doughnut — refunded', 'O-006', 'count', H('1')],
  ['Avg Order Value (reports)', 'net revenue ÷ paid orders', '50,000 ÷ 4', H('₹12,500')],
];

/* ===================== SHEET 4: Gross vs Net ===================== */
const S4_HEAD = ['Concept', 'Definition', 'Paid orders used', 'Calculation', 'Amount'];
const S4 = [
  ['Net Sales Revenue', 'Product sales only (subtotal). Dashboard "Revenue".', 'O-001..O-003, O-008', '10,000+5,000+20,000+15,000', H('₹50,000')],
  ['Shipping collected', 'Shipping charged on paid orders (pass-through)', 'same', '200+150+0+250', H('₹600')],
  ['Tax collected (GST)', 'Tax on paid orders (liability, remitted to govt)', 'same', '1,800+900+3,600+2,700', H('₹9,000')],
  ['Gross / Cash Received', 'Everything the customer paid (total). Payments "Total Received".', 'same', '50,000 + 600 + 9,000', H('₹59,600')],
  ['Difference (Gross − Net)', 'Tax + Shipping excluded from revenue', '', '600 + 9,000', H('₹9,600')],
  ['', '', '', '', ''],
  ['Why net?', 'Tax is collected on behalf of the government (a liability, not income); shipping is largely a pass-through cost. Net sales reflects true product income. The app keeps cash metrics (Received / Total Spent) on gross total so both views are available and each is internally consistent.', '', '', ''],
];

const sheets = [
  { name: 'Metrics', cols: [26, 22, 28, 26, 60, 46], rows: [S1_HEAD, ...S1] },
  { name: 'Sample Orders', cols: [11, 12, 11, 13, 13, 13, 13, 14], rows: [S2_HEAD, ...S2] },
  { name: 'Worked Example', cols: [30, 44, 40, 14], rows: [S3_HEAD, ...S3] },
  { name: 'Revenue Gross vs Net', cols: [24, 60, 22, 30, 14], rows: [S4_HEAD, ...S4] },
];
const out = path.join(__dirname, '..', 'DentInno_Dashboard_Calculations.xlsx');
buildWorkbook(sheets, out);
console.log('Wrote ' + out);
console.log('Sheets: ' + sheets.map(s => s.name + '(' + (s.rows.length - 1) + ')').join(', '));
