-- Audit trail for the most privileged surface in the system: every create/update/delete of an
-- admin account (incl. role changes) is recorded with WHO did it, the target, a change summary,
-- and when. Append-only; actor/target kept by value so deleting an admin never erases history.
CREATE TABLE IF NOT EXISTS admin_audit_log (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    actor_id     INT NULL,
    actor_name   VARCHAR(120) NULL,
    action       VARCHAR(20) NOT NULL,        -- created / updated / deleted
    target_id    INT NULL,
    target_email VARCHAR(190) NULL,
    details      VARCHAR(500) NULL,           -- human-readable change summary
    created_at   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_admin_audit_created (created_at)
);
