<?php
// Self-contained order-invoice PDF generator — NO external library (matches this project's
// no-dependency style; FPDF/Composer not used). Produces a valid single- or multi-page PDF
// (A4, Helvetica core font) good enough for an order invoice: header, bill/ship-to, an items
// table, and a totals block. Output is raw PDF bytes via buildOrderInvoicePdf().
//
// Only the WinAnsi/Latin-1 range is emitted — the rupee glyph (₹) is not in Helvetica's
// built-in encoding, so money is rendered as "Rs." to stay readable (a real font embed would
// be needed for ₹, which we deliberately avoid here to keep the file dependency-free).
require_once __DIR__ . '/config.php';

// ─────────────────────────────────────────────────────────────────────────────
// Minimal PDF writer. Coordinates are in points (1/72"); A4 = 595.28 x 841.89.
// Origin is top-left (we flip Y internally so callers think top-down).
// ─────────────────────────────────────────────────────────────────────────────
class SimplePdf {
    private float $w = 595.28, $h = 841.89;   // A4 portrait
    private float $mx = 40;                    // left/right margin
    private array $pages = [];                 // each = content-stream string
    private string $buf = '';                  // current page stream
    public  float $y;                          // current cursor Y (top-down points from top)

    public function __construct() { $this->newPage(); }

    public function newPage(): void {
        if ($this->buf !== '') $this->pages[] = $this->buf;
        $this->buf = '';
        $this->y = 50;                          // top margin
    }
    public function pageWidthUsable(): float { return $this->w - 2 * $this->mx; }
    public function marginX(): float { return $this->mx; }

    // Break to a new page if the next block of $need points won't fit.
    public function ensure(float $need): void {
        if ($this->y + $need > $this->h - 50) $this->newPage();
    }

    // Escape a string for a PDF literal ( ) and down-convert to Latin-1 (core-font safe).
    private function esc(string $s): string {
        $s = str_replace(['₹'], ['Rs.'], $s);
        // UTF-8 -> Latin-1 so accented/odd chars don't corrupt the stream; unknown -> '?'.
        $s = @iconv('UTF-8', 'Windows-1252//TRANSLIT//IGNORE', $s);
        if ($s === false) $s = '';
        return str_replace(['\\', '(', ')', "\r", "\n"], ['\\\\', '\\(', '\\)', '', ' '], $s);
    }

    // Draw text at an absolute X using the current Y. $font: 'H'=Helvetica, 'B'=Helvetica-Bold.
    public function text(float $x, string $s, float $size = 10, string $font = 'H', ?array $rgb = null): void {
        $f = $font === 'B' ? '/F2' : '/F1';
        $ty = $this->h - $this->y;              // flip to PDF bottom-up
        $color = $rgb ? sprintf("%.3f %.3f %.3f rg\n", $rgb[0] / 255, $rgb[1] / 255, $rgb[2] / 255) : "0 0 0 rg\n";
        $this->buf .= "BT\n{$color}{$f} {$size} Tf\n1 0 0 1 {$x} {$ty} Tm\n(" . $this->esc($s) . ") Tj\nET\n";
    }

    // Right-align text so its END sits at $xRight.
    public function textRight(float $xRight, string $s, float $size = 10, string $font = 'H', ?array $rgb = null): void {
        $w = $this->textWidth($s, $size, $font);
        $this->text($xRight - $w, $s, $size, $font, $rgb);
    }

    // Approximate width using Helvetica AFM-ish average widths (good enough for layout).
    public function textWidth(string $s, float $size, string $font = 'H'): float {
        // Average advance ~0.5em for Helvetica; bold a touch wider.
        $factor = $font === 'B' ? 0.54 : 0.5;
        return strlen($this->esc($s)) * $size * $factor;
    }

    public function hr(float $thickness = 0.6, ?array $rgb = null): void {
        $ty = $this->h - $this->y;
        $c = $rgb ? sprintf("%.3f %.3f %.3f RG\n", $rgb[0] / 255, $rgb[1] / 255, $rgb[2] / 255) : "0.8 0.8 0.8 RG\n";
        $this->buf .= "{$c}{$thickness} w\n{$this->mx} {$ty} m " . ($this->w - $this->mx) . " {$ty} l S\n";
    }

    // Filled rectangle (used for the table header band). x/y/w/h in points, top-down.
    public function rect(float $x, float $yTop, float $w, float $h, array $rgb): void {
        $ty = $this->h - $yTop - $h;
        $this->buf .= sprintf("%.3f %.3f %.3f rg\n%.2f %.2f %.2f %.2f re f\n", $rgb[0] / 255, $rgb[1] / 255, $rgb[2] / 255, $x, $ty, $w, $h);
    }

    public function move(float $dy): void { $this->y += $dy; }

    // Assemble the final PDF document and return raw bytes.
    public function output(): string {
        if ($this->buf !== '') { $this->pages[] = $this->buf; $this->buf = ''; }
        $objs = [];
        // 1 = Catalog, 2 = Pages, 3 = F1 (Helvetica), 4 = F2 (Helvetica-Bold).
        // Page objects + their content streams start at 5.
        $nPages = count($this->pages);
        $kids = [];
        $objNum = 5;
        $pageObjs = [];
        foreach ($this->pages as $i => $stream) {
            $contentObj = $objNum + 1;
            $pageObjs[] = $objNum;
            $kids[] = "$objNum 0 R";
            $objNum += 2;
        }
        $objs[1] = "<< /Type /Catalog /Pages 2 0 R >>";
        $objs[2] = "<< /Type /Pages /Kids [" . implode(' ', $kids) . "] /Count {$nPages} >>";
        $objs[3] = "<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>";
        $objs[4] = "<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>";
        $n = 5;
        foreach ($this->pages as $stream) {
            $contentObj = $n + 1;
            $objs[$n] = "<< /Type /Page /Parent 2 0 R /MediaBox [0 0 {$this->w} {$this->h}] "
                . "/Resources << /Font << /F1 3 0 R /F2 4 0 R >> >> /Contents {$contentObj} 0 R >>";
            $objs[$contentObj] = "<< /Length " . strlen($stream) . " >>\nstream\n{$stream}\nendstream";
            $n += 2;
        }
        ksort($objs);
        $pdf = "%PDF-1.4\n";
        $offsets = [];
        foreach ($objs as $id => $body) {
            $offsets[$id] = strlen($pdf);
            $pdf .= "{$id} 0 obj\n{$body}\nendobj\n";
        }
        $xrefPos = strlen($pdf);
        $count = max(array_keys($objs)) + 1;
        $pdf .= "xref\n0 {$count}\n0000000000 65535 f \n";
        for ($i = 1; $i < $count; $i++) {
            $pdf .= isset($offsets[$i]) ? sprintf("%010d 00000 n \n", $offsets[$i]) : "0000000000 00000 f \n";
        }
        $pdf .= "trailer\n<< /Size {$count} /Root 1 0 R >>\nstartxref\n{$xrefPos}\n%%EOF";
        return $pdf;
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// Invoice layout. Builds the PDF from an order + its items + customer.
// $order      = orders row (order_number, created_at, subtotal, discount,
//               shipping_charge, tax, total, payment_method, payment_status,
//               shipping_address JSON).
// $items      = order_items rows.
// $customer   = customers row (name, email, phone) or null.
// Returns raw PDF bytes.
// ─────────────────────────────────────────────────────────────────────────────
function buildOrderInvoicePdf(array $order, array $items, ?array $customer = null): string {
    // Company block from site_settings.company (falls back to APP_NAME).
    $company = [];
    try {
        $row = db()->fetchOne("SELECT svalue FROM site_settings WHERE skey='company'");
        $company = $row ? (json_decode($row['svalue'] ?? 'null', true) ?: []) : [];
    } catch (Throwable $e) { $company = []; }
    $coName  = (string)($company['name'] ?? APP_NAME);
    $coAddr  = (string)($company['address'] ?? '');
    $coEmail = (string)($company['email'] ?? '');
    $coPhone = (string)($company['phone'] ?? '');

    $ship = json_decode($order['shipping_address'] ?? 'null', true) ?: [];
    $money = fn($n) => 'Rs. ' . number_format((float)$n, 2);

    $p = new SimplePdf();
    $L = $p->marginX();
    $R = $L + $p->pageWidthUsable();

    // ── Header: company (left) + INVOICE + order meta (right) ──
    $p->text($L, $coName, 16, 'B', [184, 134, 11]);
    $p->textRight($R, 'INVOICE', 18, 'B', [40, 40, 40]);
    $p->move(20);
    // Truncate the address so it can't run into the right-aligned Order# on the same line.
    if ($coAddr !== '') {
        $addrMaxW = $p->pageWidthUsable() * 0.62;
        $addrFit = $coAddr;
        while ($p->textWidth($addrFit, 8) > $addrMaxW && strlen($addrFit) > 4) $addrFit = substr($addrFit, 0, -2);
        if ($addrFit !== $coAddr) $addrFit = rtrim($addrFit) . '…';
        $p->text($L, $addrFit, 8, 'H', [110, 110, 110]);
    }
    $p->textRight($R, 'Order: ' . (string)($order['order_number'] ?? ''), 10, 'B');
    $p->move(13);
    $contact = trim($coPhone . ($coPhone && $coEmail ? '  |  ' : '') . $coEmail);
    if ($contact !== '') { $p->text($L, $contact, 8, 'H', [110, 110, 110]); }
    $p->textRight($R, 'Date: ' . substr((string)($order['created_at'] ?? ''), 0, 16), 9, 'H', [110, 110, 110]);
    $p->move(14);
    $payLine = strtoupper((string)($order['payment_method'] ?? '')) . ' - ' . ucfirst((string)($order['payment_status'] ?? ''));
    $p->textRight($R, 'Payment: ' . $payLine, 9, 'H', [110, 110, 110]);
    $p->move(12);
    $p->hr(0.8, [184, 134, 11]);
    $p->move(20);

    // ── Bill To / Ship To (two columns) ──
    $colGap = 20;
    $colW   = ($p->pageWidthUsable() - $colGap) / 2;
    $rightColX = $L + $colW + $colGap;
    $topY = $p->y;

    $p->text($L, 'BILL TO', 8, 'B', [150, 150, 150]);
    $p->move(13);
    $p->text($L, (string)($customer['name'] ?? ($ship['name'] ?? '')), 10, 'B');
    $p->move(12);
    $custPhone = (string)($customer['phone'] ?? ($ship['mobile'] ?? ''));
    if ($custPhone !== '') { $p->text($L, $custPhone, 9); $p->move(11); }
    $custEmail = (string)($customer['email'] ?? '');
    if ($custEmail !== '' && !str_ends_with($custEmail, '@storefront.local')) { $p->text($L, $custEmail, 9); $p->move(11); }
    $billBottom = $p->y;

    // Ship To column (reset Y to the top of the block).
    $p->y = $topY;
    $p->text($rightColX, 'SHIP TO', 8, 'B', [150, 150, 150]);
    $p->move(13);
    $shipLines = array_filter([
        (string)($ship['name'] ?? ''),
        trim(((string)($ship['line1'] ?? $ship['building'] ?? '')) . ' ' . ((string)($ship['line2'] ?? $ship['area'] ?? ''))),
        (string)($ship['landmark'] ?? ''),
        trim(implode(', ', array_filter([$ship['city'] ?? '', $ship['district'] ?? '', $ship['state'] ?? '']))),
        !empty($ship['pincode']) ? 'PIN: ' . $ship['pincode'] : '',
    ]);
    if (!$shipLines) { $p->text($rightColX, 'Same as billing', 9, 'H', [150, 150, 150]); $p->move(11); }
    foreach ($shipLines as $i => $ln) {
        $p->text($rightColX, $ln, $i === 0 ? 10 : 9, $i === 0 ? 'B' : 'H');
        $p->move($i === 0 ? 12 : 11);
    }
    // Continue below whichever column is taller.
    $p->y = max($billBottom, $p->y) + 14;

    // ── Items table ──
    // Column x-positions: Product | Qty(center) | Unit(right) | Total(right)
    $cTotalR = $R;
    $cUnitR  = $R - 95;
    $cQtyC   = $R - 175;
    $cProd   = $L + 4;

    $p->ensure(40);
    $p->rect($L, $p->y - 3, $p->pageWidthUsable(), 18, [245, 245, 245]);
    $p->text($cProd, 'PRODUCT', 9, 'B', [80, 80, 80]);
    $p->text($cQtyC - 8, 'QTY', 9, 'B', [80, 80, 80]);
    $p->textRight($cUnitR, 'UNIT', 9, 'B', [80, 80, 80]);
    $p->textRight($cTotalR, 'TOTAL', 9, 'B', [80, 80, 80]);
    $p->move(20);

    foreach ($items as $it) {
        $name    = (string)($it['product_name'] ?? '');
        $variant = !empty($it['variant']) ? '  (' . $it['variant'] . ')' : '';
        $isGift  = (($it['line_type'] ?? '') === 'gift');
        $qty     = (int)($it['quantity'] ?? 0);
        $unit    = $isGift ? 'FREE' : $money($it['price'] ?? 0);
        $line    = $isGift ? 'FREE' : $money(((float)($it['price'] ?? 0)) * $qty);

        $p->ensure(18);
        // Truncate long product names to fit the product column.
        $maxNameW = $cQtyC - 24 - $cProd;
        $label = $name . $variant;
        while ($p->textWidth($label, 9) > $maxNameW && strlen($label) > 4) $label = substr($label, 0, -2);
        if ($label !== $name . $variant) $label = rtrim($label) . '…';

        $p->text($cProd, $label, 9);
        $p->text($cQtyC - 4, (string)$qty, 9);
        $p->textRight($cUnitR, $unit, 9, 'H', $isGift ? [39, 174, 96] : null);
        $p->textRight($cTotalR, $line, 9, 'H', $isGift ? [39, 174, 96] : null);
        $p->move(15);
        $p->hr(0.3, [235, 235, 235]);
        $p->move(3);
    }

    // ── Totals block (right-aligned) ──
    $p->move(8);
    $p->ensure(90);
    $labelR = $R - 110;
    $row = function (string $label, string $val, bool $bold = false, ?array $rgb = null) use ($p, $labelR, $cTotalR) {
        $p->textRight($labelR, $label, $bold ? 11 : 9, $bold ? 'B' : 'H', $rgb ?? [110, 110, 110]);
        $p->textRight($cTotalR, $val, $bold ? 11 : 9, $bold ? 'B' : 'H', $rgb);
        $p->move($bold ? 18 : 14);
    };
    $row('Subtotal', $money($order['subtotal'] ?? 0));
    if ((float)($order['discount'] ?? 0) > 0) $row('Discount', '- ' . $money($order['discount'] ?? 0), false, [39, 174, 96]);
    $row('Shipping', $money($order['shipping_charge'] ?? 0));
    if ((float)($order['tax'] ?? 0) > 0) $row('Tax', $money($order['tax'] ?? 0));
    $p->move(2); $p->hr(0.8, [40, 40, 40]); $p->move(8);
    $row('TOTAL', $money($order['total'] ?? 0), true, [184, 134, 11]);

    // ── Footer ──
    $p->move(24);
    $p->hr(0.4, [220, 220, 220]);
    $p->move(12);
    $p->text($L, 'Thank you for your order.', 9, 'B', [110, 110, 110]);
    $p->move(12);
    $p->text($L, 'This is a computer-generated invoice and does not require a signature.', 8, 'H', [150, 150, 150]);

    return $p->output();
}
