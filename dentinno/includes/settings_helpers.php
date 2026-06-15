<?php
// Render helpers + key-contract docs for the admin Settings page (pages/settings.php).
// Extracted from the 2100-line settings.php monolith so the markup helpers live in one
// reusable place. Behaviour is identical to the inline versions they replaced.
//
// ──────────────────────────────────────────────────────────────────────────────
// site_settings KEY CONTRACT (shared by writer + readers — keep these in sync!)
// ──────────────────────────────────────────────────────────────────────────────
// Writer:  pages/settings.php  (saveSetting/saveJson AJAX -> save_setting action)
// Readers: api/v1/settings.php (public storefront config; strips private keys)
//          api/v1/_pricing.php (tier/bulk/shipping/tax rules)
//          api/v1/home.php     (home section config)
//          React SettingsContext / _pricing.js
//
// Each row is site_settings(skey, svalue) where svalue is JSON. Known keys:
//   company            {name,email,phone,address,gst,...}        — storefront header/footer
//   banners            {hero:[...], promo:[...]}                  — home hero + promo grid
//   featured           [...]                                      — home featured showcase
//   premiumCategories  [...]                                     — home premium block
//   homeSections       [{key,type,label,enabled,...}]            — home layout order/visibility
//   tierOffers / bulkRule / shippingConfig / taxConfig           — PRICING (must match _pricing.php)
//   contactConfig / aboutConfig                                  — contact/about page content
//   socials            {facebook,instagram,...}                  — footer social links
//   otpConfig    (PRIVATE — never exposed)  {provider, fast2sms{}, twofactor{}, msg91{}}
//   whatsappConfig (PRIVATE — never exposed) {token, phoneId, templates{}}
//   orderMailConfig (PRIVATE — never exposed) {enabled, adminEmail, smtp*, from*, *Subject, *Body}
// PRIVATE keys (otpConfig, whatsappConfig, orderMailConfig) are stripped in api/v1/settings.php —
// do not add a new secret-bearing key without adding it to that $PRIVATE list.

if (!function_exists('productSelect')) {

// Product picker <select>: shows product name, value = slug. $extra = inline JS oninput.
// Reads the global $linkProducts / $linkCombos prepared by pages/settings.php.
function productSelect($id, $value, $extra = '') {
    global $linkProducts, $linkCombos;
    $opts = '<option value="">— None —</option>';
    foreach ((array)$linkProducts as $p) {
        $sel = ($p['slug'] === $value) ? 'selected' : '';
        $nm = htmlspecialchars($p['name']);
        $sl = htmlspecialchars($p['slug']);
        $opts .= "<option value=\"$sl\" $sel>$nm</option>";
    }
    foreach ((array)$linkCombos as $c) {
        $sel = ($c['slug'] === $value) ? 'selected' : '';
        $nm = htmlspecialchars('[Combo] ' . $c['name']);
        $sl = htmlspecialchars($c['slug']);
        $opts .= "<option value=\"$sl\" $sel>$nm</option>";
    }
    return "<select class=\"form-control\" id=\"$id\" $extra>$opts</select>";
}

// Image upload box: clickable dropzone + preview, hidden URL input (id holds the value).
function imgUploadBox($id, $url, $onclick) {
    $u = htmlspecialchars($url ?? '');
    $hasImg = $url ? '' : 'style="display:none;"';
    $hasPh  = $url ? 'style="display:none;"' : '';
    return <<<HTML
<div class="img-up-box" onclick="$onclick" style="border:2px dashed var(--border-active);border-radius:10px;padding:10px;text-align:center;cursor:pointer;min-height:90px;display:flex;align-items:center;justify-content:center;position:relative;">
  <input type="hidden" id="$id" value="$u">
  <img id="{$id}_prev" src="$u" $hasImg style="max-height:90px;max-width:100%;border-radius:6px;object-fit:contain;">
  <div id="{$id}_ph" $hasPh style="color:var(--text-secondary);font-size:.82rem;">
    <i class="fa-solid fa-cloud-arrow-up" style="font-size:1.5rem;color:var(--gold-primary);display:block;margin-bottom:6px;"></i>
    Click to upload image
  </div>
</div>
HTML;
}

// Render a JSON-config card with a textarea editor (echoes directly).
function settingJsonCard($key, $title, $desc, $value) {
    $json = htmlspecialchars(json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    echo <<<HTML
<div class="card fade-in" style="margin-top:18px;">
  <div class="card-header"><span class="card-title"><i class="fa-solid fa-sliders text-gold" style="margin-right:8px;"></i>$title</span><small class="text-muted">$desc</small></div>
  <div class="card-body">
    <textarea class="form-control" id="json_$key" rows="8" style="font-family:monospace;font-size:.8rem;">$json</textarea>
    <button class="btn btn-gold" style="margin-top:10px;" onclick="saveJson('$key')"><i class="fa-solid fa-floppy-disk"></i> Save $title</button>
  </div>
</div>
HTML;
}

}
