-- Brute-force throttle for the admin login. One row per client IP; after too many
-- consecutive failures the IP is locked for a cool-down window. Cleared on success.
CREATE TABLE IF NOT EXISTS admin_login_attempts (
    ip           VARCHAR(45) PRIMARY KEY,       -- IPv4/IPv6
    attempts     INT NOT NULL DEFAULT 0,
    locked_until DATETIME DEFAULT NULL,
    updated_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);
