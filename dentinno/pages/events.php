<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
$page_title = 'Events';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SERVER['HTTP_X_REQUESTED_WITH'])) {
    header('Content-Type: application/json');
    $data = json_decode(file_get_contents('php://input'), true);
    $action = $data['action'] ?? '';

    if ($action === 'save') {
        $d = $data;
        $tags_json = !empty($d['tags']) ? json_encode(array_filter(array_map('trim', explode(',', $d['tags'])))) : null;
        $is_online = !empty($d['is_online']) ? 1 : 0;
        $is_free   = !empty($d['is_free']) ? 1 : 0;
        if (!empty($d['id'])) {
            db()->execute("UPDATE events SET title=?,description=?,event_type=?,status=?,start_date=?,end_date=?,venue=?,city=?,state=?,is_online=?,online_link=?,max_attendees=?,registration_fee=?,is_free=?,organizer=?,contact_email=?,contact_phone=?,tags=? WHERE id=?",
                [$d['title'],$d['description'],$d['event_type'],$d['status'],$d['start_date'],$d['end_date'],$d['venue'],$d['city'],$d['state'],$is_online,$d['online_link'],$d['max_attendees']?:null,$d['registration_fee']??0,$is_free,$d['organizer'],$d['contact_email'],$d['contact_phone'],$tags_json,$d['id']]);
        } else {
            $slug = generateSlug($d['title']) . '-' . time();
            db()->insert("INSERT INTO events (title,slug,description,event_type,status,start_date,end_date,venue,city,state,is_online,online_link,max_attendees,registration_fee,is_free,organizer,contact_email,contact_phone,tags) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)",
                [$d['title'],$slug,$d['description'],$d['event_type'],$d['status']??'draft',$d['start_date'],$d['end_date'],$d['venue'],$d['city'],$d['state'],$is_online,$d['online_link'],$d['max_attendees']?:null,$d['registration_fee']??0,$is_free,$d['organizer'],$d['contact_email'],$d['contact_phone'],$tags_json]);
        }
        echo json_encode(['success'=>true,'message'=>'Event saved']);
    } elseif ($action === 'delete') {
        db()->execute("DELETE FROM events WHERE id=?",[$data['id']]);
        echo json_encode(['success'=>true,'message'=>'Event deleted']);
    } elseif ($action === 'change_status') {
        db()->execute("UPDATE events SET status=? WHERE id=?",[$data['status'],$data['id']]);
        echo json_encode(['success'=>true]);
    } elseif ($action === 'get_registrations') {
        $regs = db()->fetchAll("SELECT * FROM event_registrations WHERE event_id=? ORDER BY created_at DESC",[$data['event_id']]);
        echo json_encode(['success'=>true,'registrations'=>$regs]);
    } elseif ($action === 'mark_attended') {
        db()->execute("UPDATE event_registrations SET attended=? WHERE id=?",[$data['attended'],$data['id']]);
        echo json_encode(['success'=>true]);
    }
    exit;
}

$search = sanitize($_GET['search'] ?? '');
$type   = sanitize($_GET['type'] ?? '');
$status = sanitize($_GET['status'] ?? '');
$where  = ["1=1"]; $params = [];
if ($search) { $where[] = "title LIKE ?"; $params[] = "%$search%"; }
if ($type)   { $where[] = "event_type = ?"; $params[] = $type; }
if ($status) { $where[] = "status = ?"; $params[] = $status; }
$whereStr = implode(' AND ', $where);
$events = db()->fetchAll("SELECT e.*,(SELECT COUNT(*) FROM event_registrations WHERE event_id=e.id) as reg_count FROM events e WHERE $whereStr ORDER BY e.start_date DESC", $params);

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
  <button class="btn btn-gold" onclick="openEventModal()"><i class="fa-solid fa-plus"></i> Create Event</button>
</div>

<div class="filter-bar fade-in" style="flex-wrap:wrap;">
  <div class="search-wrapper" style="flex:1;min-width:180px;">
    <i class="fa-solid fa-magnifying-glass"></i>
    <input type="text" class="search-input" id="searchInput" placeholder="Search events..." value="<?= $search ?>">
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
  </select>
  <button class="btn btn-ghost btn-sm" onclick="applyFilters()"><i class="fa-solid fa-filter"></i> Filter</button>
  <a href="events.php" class="btn btn-ghost btn-sm"><i class="fa-solid fa-rotate-left"></i> Reset</a>
</div>

<!-- Events Grid -->
<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(340px,1fr));gap:18px;" class="fade-in">
  <?php foreach($events as $e): ?>
  <?php
  $typeIcons = ['conference'=>'people-group','workshop'=>'screwdriver-wrench','webinar'=>'video','exhibition'=>'store','training'=>'graduation-cap','other'=>'calendar-days'];
  $start = new DateTime($e['start_date']); $end = new DateTime($e['end_date']);
  $isPast = $end < new DateTime();
  ?>
  <div class="event-card event-status-<?= $e['status'] ?>">
    <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:12px;">
      <div style="display:flex;align-items:center;gap:8px;">
        <div style="width:36px;height:36px;background:linear-gradient(135deg,rgba(201,168,76,.2),rgba(201,168,76,.05));border-radius:9px;display:grid;place-items:center;">
          <i class="fa-solid fa-<?= $typeIcons[$e['event_type']] ?? 'calendar' ?>" style="color:var(--gold-primary);"></i>
        </div>
        <span class="event-type-badge evt-<?= $e['event_type'] ?>"><?= ucfirst($e['event_type']) ?></span>
      </div>
      <div style="display:flex;gap:5px;align-items:center;">
        <?php if($e['is_online']): ?><span class="badge badge-info" style="font-size:.68rem;">Online</span><?php endif; ?>
        <span class="badge badge-<?= ['draft'=>'secondary','published'=>'success','cancelled'=>'danger','completed'=>'info'][$e['status']] ?>"><?= ucfirst($e['status']) ?></span>
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
        <button class="btn btn-ghost btn-sm btn-icon" title="Edit" onclick='openEventModal(<?= json_encode($e) ?>)'><i class="fa-solid fa-pen"></i></button>
        <?php if($e['status']==='draft'): ?><button class="btn btn-ghost btn-sm btn-icon" title="Publish" onclick="changeEventStatus(<?= $e['id'] ?>,'published')"><i class="fa-solid fa-rocket" style="color:var(--success);"></i></button><?php endif; ?>
        <button class="btn btn-ghost btn-sm btn-icon" title="Delete" onclick="deleteEvent(<?= $e['id'] ?>)"><i class="fa-solid fa-trash" style="color:var(--danger);"></i></button>
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
  showConfirm('Delete Event','This will remove the event and all registrations. Continue?',async()=>{
    await fetch('events.php',{method:'POST',headers:{'Content-Type':'application/json','X-Requested-With':'XMLHttpRequest'},body:JSON.stringify({action:'delete',id})});
    showToast('Event deleted','success');setTimeout(()=>location.reload(),700);
  });
}
async function viewRegistrations(id,name){
  document.getElementById('regsTitle').textContent='Registrations — '+name;
  const res=await fetch('events.php',{method:'POST',headers:{'Content-Type':'application/json','X-Requested-With':'XMLHttpRequest'},body:JSON.stringify({action:'get_registrations',event_id:id})});
  const data=await res.json();
  const body=document.getElementById('regsBody');
  if(!data.registrations?.length){body.innerHTML='<div class="empty-state"><i class="fa-solid fa-users-slash"></i><p>No registrations yet</p></div>';return;}
  body.innerHTML=`<div style="margin-bottom:12px;color:var(--text-muted);font-size:.82rem;">${data.registrations.length} registrations total</div>`+
  data.registrations.map(r=>`
    <div class="reg-row">
      <div>
        <div style="font-weight:600;font-size:.9rem;">${r.name}</div>
        <div style="font-size:.78rem;color:var(--text-muted);">${r.email}${r.clinic_name?' · '+r.clinic_name:''}</div>
      </div>
      <div style="display:flex;gap:8px;align-items:center;">
        <span class="badge badge-${r.attended?'success':'secondary'}">${r.attended?'Attended':'Registered'}</span>
        <span class="badge badge-${r.payment_status==='paid'?'success':r.payment_status==='free'?'info':'warning'}">${r.payment_status}</span>
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
