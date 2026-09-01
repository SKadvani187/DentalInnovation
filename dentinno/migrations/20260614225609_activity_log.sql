-- Unified activity log: who changed which catalog/pricing/CMS/customer entity, and when.
-- Append-only; actor kept by value so deleting an admin never erases the trail. (Refunds and
-- admin-account changes keep their own richer dedicated logs.)
CREATE TABLE IF NOT EXISTS activity_log (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    actor_id    INT NULL,
    actor_name  VARCHAR(120) NULL,
    action      VARCHAR(30) NOT NULL,     -- created | updated | deleted | restored | toggled | adjusted
    entity_type VARCHAR(40) NOT NULL,     -- product | coupon | category | customer | setting | ...
    entity_id   VARCHAR(60) NULL,
    summary     VARCHAR(500) NULL,
    created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_al_created (created_at),
    INDEX idx_al_entity (entity_type, entity_id)
);
