-- Storefront login creates the customer row right after OTP (the token needs a row), with a
-- placeholder name ("Customer 1234") until the buyer completes the name-prompt step. If they
-- abandon the app at that step the placeholder used to linger in the admin Customers list.
--
-- is_provisional = 1 marks such a not-yet-named account. It is cleared the moment a real name
-- is saved (auth.php action=profile / a name-bearing login). The admin list hides provisional
-- rows so the CRM only shows real customers.
-- Idempotent: ADD COLUMN IF NOT EXISTS.

USE dentinno_crm;

ALTER TABLE customers
  ADD COLUMN IF NOT EXISTS is_provisional TINYINT(1) NOT NULL DEFAULT 0;

-- Backfill: existing placeholder-named, order-less accounts are provisional.
UPDATE customers
   SET is_provisional = 1
 WHERE is_provisional = 0
   AND total_orders = 0
   AND name REGEXP '^Customer [0-9]{4}$';
