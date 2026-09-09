-- OTP storage + rate limiting (5 attempts -> 1 hour block per identifier).

CREATE TABLE IF NOT EXISTS otp_codes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    identifier   VARCHAR(150) NOT NULL,         -- mobile number or email
    channel      ENUM('sms','email') DEFAULT 'sms',
    otp_hash     VARCHAR(255) NOT NULL,         -- hashed OTP (never store plain)
    expires_at   DATETIME NOT NULL,             -- OTP validity (e.g. +5 min)
    attempts     INT DEFAULT 0,                 -- send + verify attempts in window
    verified     TINYINT(1) DEFAULT 0,
    blocked_until DATETIME DEFAULT NULL,        -- set when attempts exceed limit
    last_sent_at DATETIME DEFAULT NULL,         -- throttle resend
    created_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_identifier (identifier)
);
