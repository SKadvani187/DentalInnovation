<?php
// Audit + notification helpers. Both are best-effort by design — a logging/notify failure must
// NEVER break the action that triggered it.

// Fields never written to the audit trail, whatever table they come from: secrets, and the
// bookkeeping columns whose change carries no information.
if (!defined('AUDIT_SKIP_FIELDS')) {
    define('AUDIT_SKIP_FIELDS', [
        'password', 'password_hash', 'api_token', 'remember_token', 'reset_token',
        'otp', 'otp_code', 'csrf_token', 'smtppass', 'smtp_pass',
        'updated_at', 'created_at', 'last_login',
    ]);
    // Per-value cap. A product's full_description or images JSON would otherwise bloat every row;
    // an audit needs to show THAT it changed and roughly to what, not to archive the content.
    define('AUDIT_VALUE_MAX', 300);
}

if (!function_exists('logActivity')) {
    /** Normalise one column value for storage: scalars kept, long text truncated, secrets dropped. */
    function auditValue($v) {
        if ($v === null) return null;
        if (is_bool($v)) return $v ? 1 : 0;
        if (is_array($v)) $v = json_encode($v, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $s = (string)$v;
        return strlen($s) > AUDIT_VALUE_MAX ? substr($s, 0, AUDIT_VALUE_MAX) . '… (' . strlen($s) . ' chars)' : $s;
    }

    /**
     * Field-level diff for the audit trail.
     *   update -> auditDiff($before, $after)  : only the columns that actually differ
     *   delete -> auditDiff($before, null)    : the whole row as it was, each "new" null
     *   create -> auditDiff(null, $after)     : the whole row as created, each "old" null
     * Returns JSON for activity_log.changes, or null when nothing worth recording changed.
     */
    function auditDiff(?array $before, ?array $after, array $extraSkip = []): ?string {
        $skip = array_merge(AUDIT_SKIP_FIELDS, array_map('strtolower', $extraSkip));
        $keys = array_unique(array_merge(array_keys($before ?? []), array_keys($after ?? [])));
        $out  = [];
        foreach ($keys as $k) {
            if (in_array(strtolower((string)$k), $skip, true)) continue;
            $hasOld = $before !== null && array_key_exists($k, $before);
            $hasNew = $after  !== null && array_key_exists($k, $after);
            $old = $hasOld ? $before[$k] : null;
            $new = $hasNew ? $after[$k]  : null;
            // Loose compare so 199 vs "199.00" from the DB doesn't read as a change.
            if ($before !== null && $after !== null) {
                if ((string)$old === (string)$new) continue;
                if (is_numeric($old) && is_numeric($new) && (float)$old === (float)$new) continue;
            }
            $out[$k] = ['old' => auditValue($old), 'new' => auditValue($new)];
        }
        if (!$out) return null;
        return json_encode($out, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /**
     * One row of $table by id, for the before/after snapshots — keeps call sites to two lines:
     *   $b = auditRow('offers', $id);  … update …  logActivity(..., auditDiff($b, auditRow('offers', $id)));
     * $table is a literal in every caller (never user input), so it is safe to interpolate.
     */
    function auditRow(string $table, $id): ?array {
        try { return db()->fetchOne("SELECT * FROM `$table` WHERE id = ?", [$id]) ?: null; }
        catch (Throwable $e) { return null; }
    }

    // Records a sensitive mutation (catalog/pricing/CMS/customer) to the global activity log.
    // $changes is the JSON from auditDiff() — what changed, from what, to what.
    function logActivity(string $action, string $entityType, $entityId = null, ?string $summary = null, ?string $changes = null): void {
        try {
            db()->insert(
                "INSERT INTO activity_log (actor_id,actor_name,action,entity_type,entity_id,summary,changes) VALUES (?,?,?,?,?,?,?)",
                [(int)($_SESSION['admin_id'] ?? 0) ?: null, $_SESSION['admin_name'] ?? null, $action, $entityType,
                 $entityId !== null ? (string)$entityId : null, $summary, $changes]
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
