<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
$page_title = 'Events';
requireView('events');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SERVER['HTTP_X_REQUESTED_WITH'])) {
    header('Content-Type: application/json');
    if (!verifyCsrf()) { http_response_code(403); echo json_encode(['success'=>false,'message'=>'Invalid CSRF token. Reload the page.']); exit; }
    // Never let a PHP warning/exception leak HTML into the JSON response (that breaks res.json()
    // on the client with "Unexpected token '<'"). Any DB error is returned as a JSON message.
    try {
    $data = json_decode(file_get_contents('php://input'), true);
    $action = $data['action'] ?? '';
    requireAction('events', rbacCrudVerb($action, $data));

    // Allowlists — keep these in sync with the badge/icon maps in the grid below so a
    // crafted request can never store an unknown status/type (which renders as a blank badge).
    $EVENT_STATUSES = ['draft','published','cancelled','completed'];
    $EVENT_TYPES    = ['workshop','webinar','conference','seminar','training','exhibition','other'];

    if ($action === 'save') {
        $d = $data;
        // ---- Server-side validation (authoritative; never trust the client) ----
        $title = trim((string)($d['title'] ?? ''));
        $start = trim((string)($d['start_date'] ?? ''));
        $end   = trim((string)($d['end_date'] ?? ''));
        if ($title === '')                      { echo json_encode(['success'=>false,'message'=>'Event title is required']); exit; }
        if ($start === '' || $end === '')       { echo json_encode(['success'=>false,'message'=>'Start and end date are required']); exit; }
        if (strtotime($end) < strtotime($start)){ echo json_encode(['success'=>false,'message'=>'End date cannot be before the start date']); exit; }
        $eventType = in_array($d['event_type'] ?? '', $validTypes, true) ? $d['event_type'] : 'other';
        $status    = in_array($d['status'] ?? '', $validStatuses, true) ? $d['status'] : 'draft';
        $is_online = !empty($d['is_online']) ? 1 : 0;
        $is_free   = !empty($d['is_free']) ? 1 : 0;
        // Validate shape before write.
        if (trim((string)($d['title'] ?? '')) === '') { echo json_encode(['success'=>false,'message'=>'Event title is required']); exit; }
        $d['event_type'] = in_array($d['event_type'] ?? '', $EVENT_TYPES, true) ? $d['event_type'] : 'other';
        $statusIn = $d['status'] ?? 'draft';
        $d['status'] = in_array($statusIn, $EVENT_STATUSES, true) ? $statusIn : 'draft';
        if (empty($d['start_date'])) { echo json_encode(['success'=>false,'message'=>'Start date is required']); exit; }
        if (empty($d['end_date']))   { $d['end_date'] = $d['start_date']; }
        if (!empty($d['contact_email']) && !filter_var($d['contact_email'], FILTER_VALIDATE_EMAIL)) { echo json_encode(['success'=>false,'message'=>'Enter a valid contact email']); exit; }
        if (!empty($d['id'])) {
            db()->execute("UPDATE events SET title=?,description=?,event_type=?,status=?,start_date=?,end_date=?,venue=?,city=?,state=?,is_online=?,online_link=?,max_attendees=?,registration_fee=?,is_free=?,organizer=?,contact_email=?,contact_phone=?,tags=? WHERE id=?",
                [$title,$desc,$eventType,$status,$start,$end,$venue,$city,$state,$is_online,$link,$maxAtt,$fee,$is_free,$organizer,$email,$phone,$tags_json,(int)$d['id']]);
        } else {
            $slug = generateSlug($title) . '-' . time();
            db()->insert("INSERT INTO events (title,slug,description,event_type,status,start_date,end_date,venue,city,state,is_online,online_link,max_attendees,registration_fee,is_free,organizer,contact_email,contact_phone,tags) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)",
                [$title,$slug,$desc,$eventType,$status,$start,$end,$venue,$city,$state,$is_online,$link,$maxAtt,$fee,$is_free,$organizer,$email,$phone,$tags_json]);
        }
        echo json_encode(['success'=>true,'message'=>'Event saved']);
    } elseif ($action === 'delete') {
        // Soft-delete: keep the event + its registrations (paid attendee data) so it can be restored.
        db()->execute("UPDATE events SET is_deleted=1 WHERE id=?",[(int)($data['id'] ?? 0)]);
        echo json_encode(['success'=>true,'message'=>'Event deleted']);
    } elseif ($action === 'restore') {
        db()->execute("UPDATE events SET is_deleted=0 WHERE id=?",[(int)($data['id'] ?? 0)]);
        echo json_encode(['success'=>true,'message'=>'Event restored']);
    } elseif ($action === 'bulk') {
        $ids = array_values(array_filter(array_map('intval', (array)($data['ids'] ?? []))));
        $op  = (string)($data['op'] ?? '');
        if (!$ids) { echo json_encode(['success'=>false,'message'=>'No events selected']); exit; }
        $ph = implode(',', array_fill(0, count($ids), '?'));
        if ($op === 'publish')     { db()->execute("UPDATE events SET status='published' WHERE id IN ($ph) AND is_deleted=0", $ids); $msg = count($ids).' event(s) published'; }
        elseif ($op === 'cancel')  { db()->execute("UPDATE events SET status='cancelled' WHERE id IN ($ph) AND is_deleted=0", $ids); $msg = count($ids).' event(s) cancelled'; }
        elseif ($op === 'delete')  { db()->execute("UPDATE events SET is_deleted=1 WHERE id IN ($ph)", $ids); $msg = count($ids).' event(s) deleted'; }
        elseif ($op === 'restore') { db()->execute("UPDATE events SET is_deleted=0 WHERE id IN ($ph)", $ids); $msg = count($ids).' event(s) restored'; }
        else { echo json_encode(['success'=>false,'message'=>'Unknown bulk action']); exit; }
        echo json_encode(['success'=>true,'message'=>$msg]);
    } elseif ($action === 'change_status') {
        $st = $data['status'] ?? '';
        if (!in_array($st, $EVENT_STATUSES, true)) { echo json_encode(['success'=>false,'message'=>'Invalid status']); exit; }
        db()->execute("UPDATE events SET status=? WHERE id=?",[$st,(int)($data['id'] ?? 0)]);
        echo json_encode(['success'=>true]);
    } elseif ($action === 'get_registrations') {
        $regs = db()->fetchAll("SELECT * FROM event_registrations WHERE event_id=? ORDER BY created_at DESC",[(int)($data['event_id'] ?? 0)]);
        echo json_encode(['success'=>true,'registrations'=>$regs]);
    } elseif ($action === 'mark_attended') {
        db()->execute("UPDATE event_registrations SET attended=? WHERE id=?",[(int)($data['attended'] ?? 0),(int)($data['id'] ?? 0)]);
        echo json_encode(['success'=>true]);
    }
    } catch (Throwable $e) {
        echo json_encode(['success'=>false,'message'=>'Server error: ' . $e->getMessage()]);
    }
    exit;
}

// CSV export of one event's registrations (check-in sheet / accounting).
if (isset($_GET['export_regs'])) {
    $eid  = (int)$_GET['export_regs'];
    $ev   = db()->fetchOne("SELECT title FROM events WHERE id=?", [$eid]);
    $regs = db()->fetchAll("SELECT name,email,phone,clinic_name,payment_status,payment_amount,registration_code,attended,created_at FROM event_registrations WHERE event_id=? ORDER BY created_at DESC", [$eid]);
    $fname = 'registrations-' . ($ev ? generateSlug($ev['title']) : 'event-'.$eid) . '.csv';
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="'.$fname.'"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Name','Email','Phone','Clinic','Payment Status','Amount','Registration Code','Attended','Registered At']);
    foreach ($regs as $r) {
        fputcsv($out, [$r['name'],$r['email'],$r['phone'],$r['clinic_name'],$r['payment_status'],$r['payment_amount'],$r['registration_code'],$r['attended']?'Yes':'No',$r['created_at']]);
    }
    fclose($out);
    exit;
}

$search = sanitize($_GET['search'] ?? '');
$type   = sanitize($_GET['type'] ?? '');
$status = sanitize($_GET['status'] ?? '');
$where  = ["1=1"]; $params = [];
if ($search) { $where[] = "title LIKE ?"; $params[] = "%$search%"; }
if ($type)   { $where[] = "event_type = ?"; $params[] = $type; }
// Soft-delete: hide deleted events unless the "Deleted" filter is chosen.
if ($status === 'deleted') {
    $where[] = "is_deleted = 1";
} else {
    $where[] = "is_deleted = 0";
    if ($status) { $where[] = "status = ?"; $params[] = $status; }
}
$whereStr = implode(' AND ', $where);
// Paginate the events grid (was: fetch ALL rows), preserving the active filters.
$page     = max(1, (int)($_GET['page'] ?? 1));
$per_page = 20;
$offset   = ($page - 1) * $per_page;
$total    = (int)(db()->fetchOne("SELECT COUNT(*) c FROM events e WHERE $whereStr", $params)['c'] ?? 0);
$pages    = (int)ceil($total / $per_page);
$events = db()->fetchAll("SELECT e.*,(SELECT COUNT(*) FROM event_registrations WHERE event_id=e.id) as reg_count FROM events e WHERE $whereStr ORDER BY e.start_date DESC LIMIT $per_page OFFSET $offset", $params);

include __DIR__ . '/../includes/header.php';
?>
<style>
.event-type-badge{padding:3px 10px;border-radius:20px;font-size:.72rem;font-weight:600;display:inline-block;}
.evt-conference{background:rgba(52,152,219,.15);color:#3498DB;}
.evt-workshop{background:rgba(155,89,182,.15);color:#9B59B6;}
.evt-webinar{background:rgba(46,204,113,.15);color:#2ECC71;}
.evt-exhibition{background:rgba(241,196,15,.15);color:#F1C40F;}
.evt-training{background:rgba(231,76,60,.15);color:#E74C3C;}
.evt-other{background:rgba(149,165,166,.15);color:#95A5A6;}
.event-card{background:var(--bg-card);border:1px solid var(--border-color);border-radius:14px;padding:20px;transition:.2s;position:relative;overflow:hidden;}
.event-card:hover{border-color:var(--border-active);transform:translateY(-2px);box-shadow:var(--shadow-gold);}
.event-card::before{content:'';position:absolute;top:0;left:0;right:0;height:3px;background:var(--gold-gradient);}
.event-status-published::before{background:linear-gradient(90deg,#2ECC71,#27AE60);}
.event-status-draft::before{background:linear-gradient(90deg,#95A5A6,#7F8C8D);}
.event-status-cancelled::before{background:linear-gradient(90deg,#E74C3C,#C0392B);}
.event-status-completed::before{background:linear-gradient(90deg,#3498DB,#2980B9);}
.reg-row{display:flex;justify-content:space-between;align-items:center;padding:10px 0;border-bottom:1px solid var(--border-color);}
.reg-row:last-child{border-bottom:none;}
</style>

<div class="page-header fade-in">
  <div class="page-header-left">
    <h1><i class="fa-solid fa-calendar-star" style="color:var(--gold-primary);margin-right:10px;"></i>Event Management</h1>
    <p>Conferences, workshops, webinars and exhibitions — <?= count($events) ?> events</p>
  </div>
  <?php if (can('events','create')): ?><button class="btn btn-gold" onclick="openEventModal()"><i class="fa-solid fa-plus"></i> Create Event</button><?php endif; ?>
</div>

<div class="filter-bar fade-in" style="flex-wrap:wrap;">
  <div class="search-wrapper" style="flex:1;min-width:180px;">
    <i class="fa-solid fa-magnifying-glass"></i>
    <input type="text" class="search-input" id="searchInput" placeholder="Search events..." value="<?= htmlspecialchars($search) ?>" onkeydown="if(event.key==='Enter')applyFilters()">
  </div>
  <select class="form-control" id="typeFilter" style="max-width:160px;">
    <option value="">All Types</option>
    <?php foreach(['conference','workshop','webinar','exhibition','training','other'] as $t): ?>
    <option value="<?= $t ?>" <?= $type===$t?'selected':'' ?>><?= ucfirst($t) ?></option>
    <?php endforeach; ?>
  </select>
  <select class="form-control" id="statusFilter" style="max-width:150px;">
    <option value="">All Status</option>
    <?php foreach(['draft','published','cancelled','completed'] as $s): ?>
    <option value="<?= $s ?>" <?= $status===$s?'selected':'' ?>><?= ucfirst($s) ?></option>
    <?php endforeach; ?>
    <option value="deleted" <?= $status==='deleted'?'selected':'' ?>>🗑 Deleted</option>
  </select>
  <button class="btn btn-ghost btn-sm" onclick="applyFilters()"><i class="fa-solid fa-filter"></i> Filter</button>
  <a href="events.php" class="btn btn-ghost btn-sm"><i class="fa-solid fa-rotate-left"></i> Reset</a>
  <label style="display:flex;align-items:center;gap:6px;font-size:.8rem;color:var(--text-secondary);cursor:pointer;margin-left:auto;">
    <input type="checkbox" id="selectAllEvents" onchange="toggleAllEvents(this)" style="width:15px;height:15px;accent-color:var(--gold-primary);cursor:pointer;"> Select all
  </label>
</div>

<div id="bulkBar" style="display:none;padding:10px 16px;margin-bottom:14px;border:1px solid var(--border-color);border-radius:10px;gap:10px;align-items:center;background:var(--bg-elevated);flex-wrap:wrap;">
  <span id="bulkCount" style="font-size:.82rem;font-weight:600;"></span>
  <?php if($status === 'deleted'): ?>
  <button class="btn btn-ghost btn-sm" onclick="bulkAction('restore')"><i class="fa-solid fa-trash-arrow-up" style="color:var(--success);"></i> Restore</button>
  <?php else: ?>
  <button class="btn btn-ghost btn-sm" onclick="bulkAction('publish')"><i class="fa-solid fa-rocket" style="color:var(--success);"></i> Publish</button>
  <button class="btn btn-ghost btn-sm" onclick="bulkAction('cancel')"><i class="fa-solid fa-ban" style="color:var(--warning);"></i> Cancel</button>
  <button class="btn btn-ghost btn-sm" onclick="bulkAction('delete')" style="color:var(--danger);"><i class="fa-solid fa-trash"></i> Delete</button>
  <?php endif; ?>
  <button class="btn btn-ghost btn-sm" onclick="clearBulk()" style="margin-left:auto;">Clear</button>
</div>

<!-- Events Grid -->
<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(340px,1fr));gap:18px;" class="fade-in">
  <?php foreach($events as $e): ?>
  <?php
  $typeIcons = ['conference'=>'people-group','workshop'=>'screwdriver-wrench','webinar'=>'video','exhibition'=>'store','training'=>'graduation-cap','seminar'=>'chalkboard-user','other'=>'calendar-days'];
  // Guard against NULL/empty/garbage dates (would otherwise throw and 500 the whole page).
  try { $start = new DateTime($e['start_date'] ?: 'now'); } catch (Exception $ex) { $start = new DateTime('now'); }
  try { $end   = new DateTime($e['end_date'] ?: ($e['start_date'] ?: 'now')); } catch (Exception $ex) { $end = $start; }
  $isPast = $end < new DateTime();
  $statusBadgeMap = ['draft'=>'secondary','published'=>'success','cancelled'=>'danger','completed'=>'info'];
  ?>
  <div class="event-card event-status-<?= $e['status'] ?>">
    <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:12px;">
      <div style="display:flex;align-items:center;gap:8px;">
        <input type="checkbox" class="event-check" value="<?= $e['id'] ?>" onchange="updateBulkBar()" style="width:15px;height:15px;accent-color:var(--gold-primary);cursor:pointer;">
        <div style="width:36px;height:36px;background:linear-gradient(135deg,rgba(201,168,76,.2),rgba(201,168,76,.05));border-radius:9px;display:grid;place-items:center;">
          <i class="fa-solid fa-<?= $typeIcons[$e['event_type']] ?? 'calendar' ?>" style="color:var(--gold-primary);"></i>
        </div>
        <span class="event-type-badge evt-<?= $e['event_type'] ?>"><?= ucfirst($e['event_type']) ?></span>
      </div>
      <div style="display:flex;gap:5px;align-items:center;">
        <?php if($isPast && !in_array($e['status'], ['cancelled','completed'], true)): ?><span class="badge badge-secondary" style="font-size:.68rem;" title="End date has passed">Past</span><?php endif; ?>
        <?php if($e['is_online']): ?><span class="badge badge-info" style="font-size:.68rem;">Online</span><?php endif; ?>
        <span class="badge badge-<?= $statusBadgeMap[$e['status']] ?? 'secondary' ?>"><?= ucfirst(htmlspecialchars($e['status'])) ?></span>
      </div>
    </div>
    <h3 style="font-family:'Playfair Display',serif;font-size:1rem;margin-bottom:8px;line-height:1.4;"><?= htmlspecialchars($e['title']) ?></h3>
    <div style="color:var(--text-muted);font-size:.78rem;margin-bottom:10px;display:flex;flex-direction:column;gap:3px;">
      <span><i class="fa-regular fa-calendar" style="width:14px;"></i> <?= $start->format('d M Y') ?> — <?= $end->format('d M Y') ?></span>
      <?php if($e['venue'] || $e['city']): ?>
      <span><i class="fa-solid fa-location-dot" style="width:14px;"></i> <?= htmlspecialchars(implode(', ', array_filter([$e['venue'],$e['city'],$e['state']]))) ?></span>
      <?php endif; ?>
      <?php if($e['organizer']): ?><span><i class="fa-solid fa-user-tie" style="width:14px;"></i> <?= htmlspecialchars($e['organizer']) ?></span><?php endif; ?>
    </div>
    <div style="display:flex;justify-content:space-between;align-items:center;margin-top:12px;padding-top:12px;border-top:1px solid var(--border-color);">
      <div style="display:flex;gap:10px;">
        <span style="font-size:.8rem;"><i class="fa-solid fa-users" style="color:var(--gold-primary);margin-right:4px;"></i><?= $e['reg_count'] ?> registered</span>
        <span style="font-size:.8rem;color:var(--gold-primary);font-weight:600;"><?= $e['is_free'] ? 'FREE' : formatCurrency($e['registration_fee']) ?></span>
      </div>
      <div style="display:flex;gap:5px;">
        <button class="btn btn-ghost btn-sm btn-icon" title="Registrations" onclick="viewRegistrations(<?= $e['id'] ?>,'<?= addslashes(htmlspecialchars($e['title'])) ?>')"><i class="fa-solid fa-users"></i></button>
        <?php if (can('events','edit')): ?><button class="btn btn-ghost btn-sm btn-icon" title="Edit" onclick='openEventModal(<?= htmlspecialchars(json_encode($e), ENT_QUOTES) ?>)'><i class="fa-solid fa-pen"></i></button><?php endif; ?>
        <?php if($e['status']==='draft'): ?><button class="btn btn-ghost btn-sm btn-icon" title="Publish" onclick="changeEventStatus(<?= $e['id'] ?>,'published')"><i class="fa-solid fa-rocket" style="color:var(--success);"></i></button><?php endif; ?>
        <?php if (can('events','delete')): ?><button class="btn btn-ghost btn-sm btn-icon" title="Delete" onclick="deleteEvent(<?= $e['id'] ?>)"><i class="fa-solid fa-trash" style="color:var(--danger);"></i></button><?php endif; ?>
      </div>
    </div>
  </div>
  <?php endforeach; ?>
  <?php if(empty($events)): ?>
  <div class="card" style="padding:40px;text-align:center;grid-column:1/-1;">
    <i class="fa-solid fa-calendar-xmark" style="font-size:2.5rem;color:var(--text-muted);margin-bottom:12px;"></i>
    <p style="color:var(--text-muted);">No events found. <a href="#" onclick="openEventModal()" style="color:var(--gold-primary);">Create your first event</a></p>
  </div>
  <?php endif; ?>
</div>

<?php if($pages > 1): $qs = http_build_query(array_filter(['search'=>$search,'type'=>$type,'status'=>$status])); $qp = $qs ? $qs.'&' : ''; ?>
<div class="pagination" style="margin-top:16px;">
    <?php
    // Compact pagination: first, last, and a window around the current page (… for gaps).
    $range = 2; $shown = [];
    for ($i = 1; $i <= $pages; $i++) {
        if ($i == 1 || $i == $pages || ($i >= $page - $range && $i <= $page + $range)) $shown[] = $i;
    }
    if ($page > 1): ?><a href="?<?= $qp ?>page=<?= $page-1 ?>" class="page-item" style="text-decoration:none;">‹</a><?php endif;
    $prev = 0;
    foreach ($shown as $i):
        if ($prev && $i - $prev > 1): ?><span class="page-item" style="pointer-events:none;opacity:.5;">…</span><?php endif; ?>
        <a href="?<?= $qp ?>page=<?= $i ?>" class="page-item <?= $i==$page?'active':'' ?>" style="text-decoration:none;"><?= $i ?></a>
        <?php $prev = $i;
    endforeach;
    if ($page < $pages): ?><a href="?<?= $qp ?>page=<?= $page+1 ?>" class="page-item" style="text-decoration:none;">›</a><?php endif; ?>
</div>
<?php endif; ?>

<!-- EVENT MODAL -->
<div class="modal-overlay" id="eventModal" style="display:none;" onclick="if(event.target===this)closeModal('eventModal')">
  <div class="modal-box" style="max-width:700px;width:96vw;">
    <div class="modal-head"><h2 id="eventModalTitle">Create Event</h2><button class="close-btn" onclick="closeModal('eventModal')"><i class="fa-solid fa-xmark"></i></button></div>
    <div class="modal-body" style="max-height:65vh;overflow-y:auto;">
      <input type="hidden" id="event_id">
      <div class="form-group"><label class="form-label">Event Title *</label><input type="text" class="form-control" id="event_title" placeholder="e.g. DentInno Annual Conference 2025"></div>
      <div class="form-row">
        <div class="form-group"><label class="form-label">Event Type</label>
          <select class="form-control" id="event_type">
            <?php foreach(['conference','workshop','webinar','exhibition','training','other'] as $t): ?><option value="<?= $t ?>"><?= ucfirst($t) ?></option><?php endforeach; ?>
          </select>
        </div>
        <div class="form-group"><label class="form-label">Status</label>
          <select class="form-control" id="event_status">
            <?php foreach(['draft','published','cancelled','completed'] as $s): ?><option value="<?= $s ?>"><?= ucfirst($s) ?></option><?php endforeach; ?>
          </select>
        </div>
      </div>
      <div class="form-group"><label class="form-label">Description</label><textarea class="form-control" id="event_desc" rows="3" placeholder="Event description..."></textarea></div>
      <div class="form-row">
        <div class="form-group"><label class="form-label">Start Date & Time *</label><input type="datetime-local" class="form-control" id="event_start"></div>
        <div class="form-group"><label class="form-label">End Date & Time *</label><input type="datetime-local" class="form-control" id="event_end"></div>
      </div>
      <div style="margin-bottom:14px;display:flex;align-items:center;gap:10px;">
        <label style="display:flex;align-items:center;gap:7px;cursor:pointer;"><input type="checkbox" id="event_online" style="width:15px;height:15px;accent-color:var(--gold-primary);" onchange="document.getElementById('online_link_wrap').style.display=this.checked?'block':'none';document.getElementById('venue_wrap').style.display=this.checked?'none':'block';"><span class="form-label" style="margin:0;">Online Event</span></label>
      </div>
      <div id="venue_wrap">
        <div class="form-group"><label class="form-label">Venue / Location</label><input type="text" class="form-control" id="event_venue" placeholder="Hall name, address..."></div>
        <div class="form-row">
          <div class="form-group"><label class="form-label">City</label><input type="text" class="form-control" id="event_city" placeholder="Mumbai"></div>
          <div class="form-group"><label class="form-label">State</label><input type="text" class="form-control" id="event_state" placeholder="Maharashtra"></div>
        </div>
      </div>
      <div id="online_link_wrap" style="display:none;">
        <div class="form-group"><label class="form-label">Online Meeting Link</label><input type="url" class="form-control" id="event_link" placeholder="https://meet.google.com/..."></div>
      </div>
      <div class="form-row">
        <div class="form-group"><label class="form-label">Max Attendees</label><input type="number" class="form-control" id="event_max" placeholder="Unlimited"></div>
        <div class="form-group">
          <label class="form-label">Registration Fee (₹)</label>
          <input type="number" class="form-control" id="event_fee" placeholder="0">
          <label style="display:flex;align-items:center;gap:6px;margin-top:6px;cursor:pointer;"><input type="checkbox" id="event_free" style="width:14px;height:14px;accent-color:var(--gold-primary);" onchange="document.getElementById('event_fee').disabled=this.checked;if(this.checked)document.getElementById('event_fee').value=0;"><span style="font-size:.8rem;color:var(--text-secondary);">Free Event</span></label>
        </div>
      </div>
      <div class="form-row">
        <div class="form-group"><label class="form-label">Organizer</label><input type="text" class="form-control" id="event_organizer" placeholder="DentInno"></div>
        <div class="form-group"><label class="form-label">Contact Email</label><input type="email" class="form-control" id="event_email" placeholder="events@dentinno.com"></div>
      </div>
      <div class="form-row">
        <div class="form-group"><label class="form-label">Contact Phone</label><input type="text" class="form-control" id="event_phone" placeholder="+91..."></div>
        <div class="form-group"><label class="form-label">Tags <small class="text-muted">(comma-separated)</small></label><input type="text" class="form-control" id="event_tags" placeholder="implants, surgery, CE"></div>
      </div>
    </div>
    <div class="modal-foot">
      <button class="btn btn-ghost" onclick="closeModal('eventModal')">Cancel</button>
      <button class="btn btn-gold" onclick="saveEvent()"><i class="fa-solid fa-floppy-disk"></i> Save Event</button>
    </div>
  </div>
</div>

<!-- REGISTRATIONS MODAL -->
<div class="modal-overlay" id="regsModal" style="display:none;" onclick="if(event.target===this)closeModal('regsModal')">
  <div class="modal-box" style="max-width:680px;">
    <div class="modal-head"><h2 id="regsTitle">Registrations</h2><button class="close-btn" onclick="closeModal('regsModal')"><i class="fa-solid fa-xmark"></i></button></div>
    <div class="modal-body" id="regsBody" style="max-height:60vh;overflow-y:auto;"></div>
  </div>
</div>

<script>
function applyFilters(){window.location.href=`events.php?search=${encodeURIComponent(document.getElementById('searchInput').value)}&type=${document.getElementById('typeFilter').value}&status=${document.getElementById('statusFilter').value}`;}

// ---- Bulk selection ----
function selectedEventIds(){return [...document.querySelectorAll('.event-check:checked')].map(c=>parseInt(c.value));}
function updateBulkBar(){
  const n=selectedEventIds().length;
  const bar=document.getElementById('bulkBar');
  bar.style.display=n?'flex':'none';
  if(n)document.getElementById('bulkCount').textContent=n+' selected';
  const all=document.getElementById('selectAllEvents'); const total=document.querySelectorAll('.event-check').length;
  if(all)all.checked=n>0&&n===total;
}
function toggleAllEvents(cb){document.querySelectorAll('.event-check').forEach(c=>c.checked=cb.checked);updateBulkBar();}
function clearBulk(){document.querySelectorAll('.event-check').forEach(c=>c.checked=false);const a=document.getElementById('selectAllEvents');if(a)a.checked=false;updateBulkBar();}
async function bulkAction(op){
  const ids=selectedEventIds(); if(!ids.length)return;
  if(!confirm(`${op.charAt(0).toUpperCase()+op.slice(1)} ${ids.length} event(s)?`))return;
  const res=await fetch('events.php',{method:'POST',headers:{'Content-Type':'application/json','X-Requested-With':'XMLHttpRequest'},body:JSON.stringify({action:'bulk',op,ids})});
  const r=await res.json();
  showToast(r.message||(r.success?'Done':'Failed'), r.success?'success':'danger');
  if(r.success)setTimeout(()=>location.reload(),800);
}
function openEventModal(e=null){
  document.getElementById('event_id').value=e?.id||'';
  document.getElementById('event_title').value=e?.title||'';
  document.getElementById('event_type').value=e?.event_type||'conference';
  document.getElementById('event_status').value=e?.status||'draft';
  document.getElementById('event_desc').value=e?.description||'';
  document.getElementById('event_start').value=e?.start_date?e.start_date.replace(' ','T').slice(0,16):'';
  document.getElementById('event_end').value=e?.end_date?e.end_date.replace(' ','T').slice(0,16):'';
  document.getElementById('event_venue').value=e?.venue||'';
  document.getElementById('event_city').value=e?.city||'';
  document.getElementById('event_state').value=e?.state||'';
  document.getElementById('event_link').value=e?.online_link||'';
  document.getElementById('event_max').value=e?.max_attendees||'';
  document.getElementById('event_fee').value=e?.registration_fee||'';
  document.getElementById('event_organizer').value=e?.organizer||'';
  document.getElementById('event_email').value=e?.contact_email||'';
  document.getElementById('event_phone').value=e?.contact_phone||'';
  try{const tags=e?.tags?JSON.parse(e.tags):[];document.getElementById('event_tags').value=tags.join(', ');}catch(e){document.getElementById('event_tags').value='';}
  const isOnline=!!(e?.is_online);
  document.getElementById('event_online').checked=isOnline;
  document.getElementById('venue_wrap').style.display=isOnline?'none':'block';
  document.getElementById('online_link_wrap').style.display=isOnline?'block':'none';
  const isFree=!!(e?.is_free);
  document.getElementById('event_free').checked=isFree;
  document.getElementById('event_fee').disabled=isFree;
  document.getElementById('eventModalTitle').textContent=e?'Edit Event':'Create Event';
  openModal('eventModal');
}
async function saveEvent(){
  const title=document.getElementById('event_title').value.trim();
  const start=document.getElementById('event_start').value;
  const end=document.getElementById('event_end').value;
  if(!title||!start||!end){showToast('Title, start and end date are required','warning');return;}
  if(new Date(end) < new Date(start)){showToast('End date cannot be before the start date','warning');return;}
  const payload={action:'save',id:document.getElementById('event_id').value,title,
    event_type:document.getElementById('event_type').value,status:document.getElementById('event_status').value,
    description:document.getElementById('event_desc').value,start_date:start.replace('T',' '),end_date:end.replace('T',' '),
    venue:document.getElementById('event_venue').value,city:document.getElementById('event_city').value,
    state:document.getElementById('event_state').value,is_online:document.getElementById('event_online').checked?1:0,
    online_link:document.getElementById('event_link').value,max_attendees:document.getElementById('event_max').value,
    registration_fee:document.getElementById('event_fee').value||0,is_free:document.getElementById('event_free').checked?1:0,
    organizer:document.getElementById('event_organizer').value,contact_email:document.getElementById('event_email').value,
    contact_phone:document.getElementById('event_phone').value,tags:document.getElementById('event_tags').value};
  const res=await fetch('events.php',{method:'POST',headers:{'Content-Type':'application/json','X-Requested-With':'XMLHttpRequest'},body:JSON.stringify(payload)});
  const r=await res.json();if(r.success){showToast(r.message,'success');closeModal('eventModal');setTimeout(()=>location.reload(),700);}
  else showToast(r.message||'Error saving event','danger');
}
async function changeEventStatus(id,status){
  await fetch('events.php',{method:'POST',headers:{'Content-Type':'application/json','X-Requested-With':'XMLHttpRequest'},body:JSON.stringify({action:'change_status',id,status})});
  showToast('Event published!','success');setTimeout(()=>location.reload(),600);
}
function deleteEvent(id){
  showConfirm('Delete Event','This hides the event from the storefront. Its registrations are kept, and you can restore it from the "Deleted" filter. Continue?',async()=>{
    await fetch('events.php',{method:'POST',headers:{'Content-Type':'application/json','X-Requested-With':'XMLHttpRequest'},body:JSON.stringify({action:'delete',id})});
    showToast('Event deleted','success');setTimeout(()=>location.reload(),700);
  });
}
function restoreEvent(id){
  fetch('events.php',{method:'POST',headers:{'Content-Type':'application/json','X-Requested-With':'XMLHttpRequest'},body:JSON.stringify({action:'restore',id})})
  .then(r=>r.json()).then(d=>{if(d.success){showToast('Event restored','success');setTimeout(()=>location.reload(),600);}});
}
async function viewRegistrations(id,name){
  document.getElementById('regsTitle').textContent='Registrations — '+name;
  const res=await fetch('events.php',{method:'POST',headers:{'Content-Type':'application/json','X-Requested-With':'XMLHttpRequest'},body:JSON.stringify({action:'get_registrations',event_id:id})});
  const data=await res.json();
  const body=document.getElementById('regsBody');
  if(!data.registrations?.length){body.innerHTML='<div class="empty-state"><i class="fa-solid fa-users-slash"></i><p>No registrations yet</p></div>';return;}
  body.innerHTML=`<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;">
      <span style="color:var(--text-muted);font-size:.82rem;">${data.registrations.length} registrations total</span>
      <a href="events.php?export_regs=${id}" class="btn btn-ghost btn-sm"><i class="fa-solid fa-file-csv"></i> Export CSV</a>
    </div>`+
  data.registrations.map(r=>`
    <div class="reg-row">
      <div>
        <div style="font-weight:600;font-size:.9rem;">${escapeHtml(r.name)}</div>
        <div style="font-size:.78rem;color:var(--text-muted);">${escapeHtml(r.email)}${r.clinic_name?' · '+escapeHtml(r.clinic_name):''}</div>
      </div>
      <div style="display:flex;gap:8px;align-items:center;">
        <span class="badge badge-${r.attended?'success':'secondary'}">${r.attended?'Attended':'Registered'}</span>
        <span class="badge badge-${r.payment_status==='paid'?'success':r.payment_status==='free'?'info':'warning'}">${escapeHtml(r.payment_status||'')}</span>
        <button class="btn btn-ghost btn-sm btn-icon" onclick="toggleAttendance(${r.id},${r.attended?0:1})" title="${r.attended?'Mark Absent':'Mark Present'}">
          <i class="fa-solid fa-${r.attended?'user-minus':'user-check'}" style="color:${r.attended?'var(--warning)':'var(--success)'}"></i>
        </button>
      </div>
    </div>`).join('');
  openModal('regsModal');
}
async function toggleAttendance(id,attended){
  await fetch('events.php',{method:'POST',headers:{'Content-Type':'application/json','X-Requested-With':'XMLHttpRequest'},body:JSON.stringify({action:'mark_attended',id,attended})});
  showToast('Attendance updated','success');closeModal('regsModal');
}
</script>
<?php include __DIR__ . '/../includes/footer.php'; ?>
