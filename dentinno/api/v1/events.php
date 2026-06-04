<?php
// GET /api/v1/events.php           -> published events
// GET /api/v1/events.php?slug=ev-001 -> single event
require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/_map.php';

$slug = qstr('slug');
if ($slug !== '') {
    $row = db()->fetchOne("SELECT * FROM events WHERE slug=? AND status='published'", [$slug]);
    if (!$row) jsonErr('Event not found', 404);
    jsonOut(['success' => true, 'event' => mapEvent($row)]);
}
$rows = db()->fetchAll("SELECT * FROM events WHERE status='published' ORDER BY id DESC");
jsonOut(['success' => true, 'events' => array_map('mapEvent', $rows)]);
