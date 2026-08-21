// Dependency-free .xlsx generator for the DentInno admin-panel security/bug fixes.
// Produces a multi-sheet workbook (Test Cases, Issues Summary, Roles Reference).
// Uses only Node built-ins (Buffer/zlib not even needed — ZIP entries are STORED).
const fs = require('fs');
const path = require('path');

/* ---------------- ZIP (stored) + CRC32 ---------------- */
const CRC_TABLE = (() => { let c, t = []; for (let n = 0; n < 256; n++) { c = n; for (let k = 0; k < 8; k++) c = (c & 1) ? (0xEDB88320 ^ (c >>> 1)) : (c >>> 1); t[n] = c >>> 0; } return t; })();
function crc32(buf) { let crc = 0xFFFFFFFF; for (let i = 0; i < buf.length; i++) crc = (crc >>> 8) ^ CRC_TABLE[(crc ^ buf[i]) & 0xFF]; return (crc ^ 0xFFFFFFFF) >>> 0; }

function zip(files) {
  const locals = []; const central = []; let offset = 0;
  for (const f of files) {
    const name = Buffer.from(f.name, 'utf8');
    const data = Buffer.isBuffer(f.data) ? f.data : Buffer.from(f.data, 'utf8');
    const crc = crc32(data);
    const lh = Buffer.alloc(30);
    lh.writeUInt32LE(0x04034b50, 0); lh.writeUInt16LE(20, 4); lh.writeUInt16LE(0, 6);
    lh.writeUInt16LE(0, 8); lh.writeUInt16LE(0, 10); lh.writeUInt16LE(0, 12);
    lh.writeUInt32LE(crc, 14); lh.writeUInt32LE(data.length, 18); lh.writeUInt32LE(data.length, 22);
    lh.writeUInt16LE(name.length, 26); lh.writeUInt16LE(0, 28);
    locals.push(lh, name, data);
    const ch = Buffer.alloc(46);
    ch.writeUInt32LE(0x02014b50, 0); ch.writeUInt16LE(20, 4); ch.writeUInt16LE(20, 6);
    ch.writeUInt16LE(0, 8); ch.writeUInt16LE(0, 10); ch.writeUInt16LE(0, 12); ch.writeUInt16LE(0, 14);
    ch.writeUInt32LE(crc, 16); ch.writeUInt32LE(data.length, 20); ch.writeUInt32LE(data.length, 24);
    ch.writeUInt16LE(name.length, 28); ch.writeUInt16LE(0, 30); ch.writeUInt16LE(0, 32);
    ch.writeUInt16LE(0, 34); ch.writeUInt16LE(0, 36); ch.writeUInt32LE(0, 38); ch.writeUInt32LE(offset, 42);
    central.push(ch, name);
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

// style indexes (see styles.xml): 1=header, 2=body, 3=body-center
function sheetXml(rows, cols, opts = {}) {
  const colsXml = cols.map((w, i) => `<col min="${i + 1}" max="${i + 1}" width="${w}" customWidth="1"/>`).join('');
  let body = '';
  rows.forEach((row, r) => {
    const rn = r + 1;
    const cells = row.map((cell, c) => {
      const ref = colLetter(c) + rn;
      const style = (r === 0) ? 1 : (cell && cell.center ? 3 : 2);
      const val = (cell && typeof cell === 'object') ? cell.v : cell;
      return `<c r="${ref}" s="${style}" t="inlineStr"><is><t xml:space="preserve">${esc(val)}</t></is></c>`;
    }).join('');
    body += `<row r="${rn}"${r === 0 ? ' ht="26" customHeight="1"' : ''}>${cells}</row>`;
  });
  const freeze = `<sheetViews><sheetView workbookViewId="0"><pane ySplit="1" topLeftCell="A2" activePane="bottomLeft" state="frozen"/><selection pane="bottomLeft"/></sheetView></sheetViews>`;
  const auto = `<autoFilter ref="A1:${colLetter(cols.length - 1)}${rows.length}"/>`;
  return `<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">${freeze}<cols>${colsXml}</cols><sheetData>${body}</sheetData>${auto}</worksheet>`;
}

const stylesXml = `<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">
<fonts count="2"><font><sz val="10"/><name val="Calibri"/></font><font><b/><sz val="10"/><color rgb="FFFFFFFF"/><name val="Calibri"/></font></fonts>
<fills count="3"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill><fill><patternFill patternType="solid"><fgColor rgb="FF1F2A44"/><bgColor indexed="64"/></patternFill></fill></fills>
<borders count="2"><border><left/><right/><top/><bottom/><diagonal/></border><border><left style="thin"><color rgb="FFCCCCCC"/></left><right style="thin"><color rgb="FFCCCCCC"/></right><top style="thin"><color rgb="FFCCCCCC"/></top><bottom style="thin"><color rgb="FFCCCCCC"/></bottom></border></borders>
<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>
<cellXfs count="4">
<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>
<xf numFmtId="0" fontId="1" fillId="2" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="center" wrapText="1"/></xf>
<xf numFmtId="0" fontId="0" fillId="0" borderId="1" xfId="0" applyBorder="1" applyAlignment="1"><alignment vertical="top" wrapText="1"/></xf>
<xf numFmtId="0" fontId="0" fillId="0" borderId="1" xfId="0" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="top" wrapText="1"/></xf>
</cellXfs></styleSheet>`;

function buildWorkbook(sheets, outPath) {
  const ct = `<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
<Default Extension="xml" ContentType="application/xml"/>
<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>
${sheets.map((s, i) => `<Override PartName="/xl/worksheets/sheet${i + 1}.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>`).join('')}
<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>
</Types>`;
  const rels = `<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>`;
  const wb = `<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets>
${sheets.map((s, i) => `<sheet name="${esc(s.name)}" sheetId="${i + 1}" r:id="rId${i + 1}"/>`).join('')}
</sheets></workbook>`;
  const wbRels = `<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
${sheets.map((s, i) => `<Relationship Id="rId${i + 1}" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet${i + 1}.xml"/>`).join('')}
<Relationship Id="rId${sheets.length + 1}" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>
</Relationships>`;
  const files = [
    { name: '[Content_Types].xml', data: ct },
    { name: '_rels/.rels', data: rels },
    { name: 'xl/workbook.xml', data: wb },
    { name: 'xl/_rels/workbook.xml.rels', data: wbRels },
    { name: 'xl/styles.xml', data: stylesXml },
    ...sheets.map((s, i) => ({ name: `xl/worksheets/sheet${i + 1}.xml`, data: sheetXml(s.rows, s.cols) })),
  ];
  fs.writeFileSync(outPath, zip(files));
}

/* ====================== DATA ====================== */
const { TEST_ROWS, ISSUE_ROWS, ROLE_ROWS } = require('./testcases_data.js');

const TC_COLS = [9, 9, 10, 17, 13, 30, 24, 52, 28, 40, 9];
const TC_HEAD = ['TC ID', 'Issue ID', 'Severity', 'Module / Page', 'Category', 'Test Scenario', 'Preconditions', 'Test Steps', 'Test Data', 'Expected Result', 'Status'];

const ISS_COLS = [9, 10, 17, 26, 48, 52];
const ISS_HEAD = ['Issue ID', 'Severity', 'Area', 'File(s)', 'Issue (before)', 'Fix Applied'];

const ROLE_COLS = [16, 72, 40];
const ROLE_HEAD = ['Role', 'Permissions (write actions)', 'Notes'];

const sheets = [
  { name: 'Test Cases', cols: TC_COLS, rows: [TC_HEAD, ...TEST_ROWS] },
  { name: 'Issues Summary', cols: ISS_COLS, rows: [ISS_HEAD, ...ISSUE_ROWS] },
  { name: 'Roles Reference', cols: ROLE_COLS, rows: [ROLE_HEAD, ...ROLE_ROWS] },
];

const out = path.join(__dirname, '..', 'DentInno_Admin_Panel_Test_Cases.xlsx');
buildWorkbook(sheets, out);
console.log('Wrote ' + out);
console.log('Test cases: ' + TEST_ROWS.length + ' | Issues: ' + ISSUE_ROWS.length + ' | Roles: ' + ROLE_ROWS.length);
