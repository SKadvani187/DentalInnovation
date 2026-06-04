<?php
// GET /api/v1/ -> API info + route list (health check)
require_once __DIR__ . '/_bootstrap.php';

jsonOut([
    'success' => true,
    'name'    => 'Dentinno Storefront API',
    'version' => 'v1',
    'routes'  => [
        'GET /api/v1/products.php'              => 'list products (category, search, sort, min, max, page, limit)',
        'GET /api/v1/products.php?slug=p-001'   => 'single product',
        'GET /api/v1/categories.php'            => 'all categories',
        'GET /api/v1/combos.php'                => 'all combos',
        'GET /api/v1/combos.php?slug=c-001'     => 'single combo',
        'GET /api/v1/events.php'                => 'published events',
        'GET /api/v1/offers.php'                => 'active offers',
        'GET /api/v1/testimonials.php'          => 'active testimonials',
        'GET /api/v1/home.php'                  => 'combined home feed (one call)',
        'POST /api/v1/otp.php?action=request'   => 'send OTP (sms/email) with rate limit',
        'POST /api/v1/otp.php?action=verify'    => 'verify OTP',
        'POST /api/v1/auth.php?action=login'    => 'customer login/register by mobile -> token',
        'GET /api/v1/auth.php?action=me'        => 'current customer (Bearer token)',
        'POST /api/v1/auth.php?action=profile'  => 'update profile (Bearer token)',
        'POST /api/v1/orders.php'               => 'place order (Bearer token)',
        'GET /api/v1/orders.php'                => 'list my orders (Bearer token)',
        'GET /api/v1/coupon.php?code=X&subtotal=N' => 'validate a coupon',
        'GET /api/v1/wishlist.php'              => 'get my wishlist (Bearer token)',
        'POST /api/v1/wishlist.php'             => 'sync my wishlist (Bearer token)',
    ],
]);
