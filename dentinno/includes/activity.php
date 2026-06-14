<?php
// Audit + notification helpers. Both are best-effort by design — a logging/notify failure must
// NEVER break the action that triggered it.

if (!function_exists('logActivity')) {
    // Records a sensitive mutation (catalog/pricing/CMS/customer) to the global activity log.
    function logActivity(string $action, string $entityType, $entityId = null, ?string $summary = null): void {
        try {
            db()->insert(
                "INSERT INTO activity_log (actor_id,actor_name,action,entity_type,entity_id,summary) VALUES (?,?,?,?,?,?)",
                [(int)($_SESSION['admin_id'] ?? 0) ?: null, $_SESSION['admin_name'] ?? null, $action, $entityType,
                 $entityId !== null ? (string)$entityId : null, $summary]
            );
        } catch (Throwable $e) { /* never break the caller */ }
    }
}

if (!function_exists('pushNotification')) {
    // Inserts a header notification. type ∈ order|payment|stock|customer|system (drives the icon).
    function pushNotification(string $type, string $title, string $message, ?string $link = null): void {
        try {
            db()->insert(
                "INSERT INTO notifications (title,message,type,link,is_read) VALUES (?,?,?,?,0)",
                [$title, $message, $type, $link]
            );
        } catch (Throwable $e) { /* never break the caller */ }
    }
}
