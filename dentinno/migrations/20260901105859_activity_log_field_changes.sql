-- activity_log_field_changes
--
-- Field-level before/after for the audit trail. `summary` only ever said WHAT was touched
-- ("Paper Points Pack Sterile · ₹299"); this says WHICH FIELDS changed and from what to what,
-- which is the part an audit actually needs.
--
-- Shape: {"field": {"old": <value|null>, "new": <value|null>}, ...}
--   update -> only the fields that differ
--   delete -> the whole row as it was, with "new": null
-- Long values are truncated by the writer (see auditDiff in includes/activity.php), and secrets
-- (password, api_token, …) are never recorded.
--
-- LONGTEXT, not JSON: MariaDB aliases JSON to LONGTEXT anyway, and this keeps the column
-- readable to the older MySQL builds some shared hosts still run.

ALTER TABLE activity_log ADD COLUMN IF NOT EXISTS changes LONGTEXT NULL AFTER summary;
