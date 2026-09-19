<?php
/**
 * Formatting for the delivery address captured on an order (orders.shipping_address JSON).
 *
 * The storefront sends ONE shape, and has done for every order in the system:
 *     { name, phone, address, city, state, pincode }
 * where `address` is already a full composed line ("90/729 Pushpak Appartment, 132 ft ring
 * road, Ahmedabad, Gujarat, 380013") — city, state and pincode are repeated inside it.
 *
 * The admin, the invoice PDF and the packing slip were each reading a DIFFERENT shape
 * (line1 / building / line2 / area / landmark / district / mobile) that no order has ever
 * contained, so the street address and phone silently vanished and only "Ahmedabad, Gujarat
 * — 380013" survived. Parcels cannot be delivered to that.
 *
 * These helpers read the real shape first, keep the older key names as a fallback in case an
 * order is ever created with them, and — importantly — do NOT append city/state/pincode again
 * when `address` already carries them.
 */

/** The phone number on a delivery address, or '' when none was captured. */
function orderAddressPhone(array $ship): string
{
    return trim((string)($ship['mobile'] ?? $ship['phone'] ?? ''));
}

/**
 * The address as a list of display lines (name excluded), most specific first.
 * Used by the invoice and packing slip, which print one line at a time.
 */
function orderAddressLines(array $ship): array
{
    $composed = trim((string)($ship['address'] ?? ''));
    $tail = trim(implode(', ', array_filter([
        trim((string)($ship['city'] ?? '')),
        trim((string)($ship['district'] ?? '')),
        trim((string)($ship['state'] ?? '')),
    ])));
    $pin = trim((string)($ship['pincode'] ?? ''));

    if ($composed !== '') {
        // `address` already ends with city, state and pincode — adding them again would read
        // "…Ahmedabad, Gujarat, 380013, Ahmedabad, Gujarat — 380013".
        $lines = [$composed];
        if ($pin !== '' && strpos($composed, $pin) === false) $lines[] = 'PIN: ' . $pin;
        return $lines;
    }

    // Older / admin-entered shape: build the line from its parts.
    $street = trim(
        (string)($ship['line1'] ?? $ship['building'] ?? '') . ' ' .
        (string)($ship['line2'] ?? $ship['area'] ?? '')
    );
    return array_values(array_filter([
        $street,
        trim((string)($ship['landmark'] ?? '')),
        $tail,
        $pin !== '' ? 'PIN: ' . $pin : '',
    ], static fn($v) => trim((string)$v) !== ''));
}

/** The whole address as a single line — for the order detail screen and emails. */
function orderAddressLine(array $ship): string
{
    return implode(', ', orderAddressLines($ship));
}
