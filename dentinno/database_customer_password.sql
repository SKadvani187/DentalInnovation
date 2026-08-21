-- Storefront login moved from OTP-only to password-based for returning customers.
-- A new buyer still verifies via OTP once, then sets a password (name + password + confirm
-- on the profile step). On every later login they enter that password instead of an OTP —
-- so no OTP is generated for a known account.
--
-- password = bcrypt hash (password_hash) of the buyer's chosen password. NULL means the
-- account has no password yet (legacy OTP-only accounts) — those fall back to the OTP flow,
-- which then prompts them to set a password.
-- Idempotent: ADD COLUMN IF NOT EXISTS.

USE dentinno_crm;

ALTER TABLE customers
  ADD COLUMN IF NOT EXISTS password VARCHAR(255) DEFAULT NULL;
