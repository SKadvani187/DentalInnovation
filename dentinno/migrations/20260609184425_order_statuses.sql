-- Extend the orders.status enum with the full storefront lifecycle so every "Order Type"
-- filter (and the admin status dropdown) maps to a real, settable status.
-- Adds: out_for_delivery, returned, returning, rejected.
ALTER TABLE orders
  MODIFY COLUMN status ENUM(
    'pending','processing','confirmed','shipped','out_for_delivery',
    'delivered','cancelled','returning','returned','rejected','refunded'
  ) NOT NULL DEFAULT 'pending';
