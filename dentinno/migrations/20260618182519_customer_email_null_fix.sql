-- Fix: customers.email is UNIQUE. MySQL permits many NULLs but rejects duplicate ''.
-- The storefront sent email='' for buyers with no email, so the 2nd such buyer hit
-- 1062 Duplicate entry '' for key 'email' on completeProfile -> "Couldn't save your name".
-- Normalize any existing blank emails to NULL. (Backend now coerces blank email -> NULL
-- in api/v1/auth.php action=profile, so new blanks never collide.)
UPDATE customers SET email = NULL WHERE email = '';
