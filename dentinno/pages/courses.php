<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
$page_title = 'Courses';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SERVER['HTTP_X_REQUESTED_WITH'])) {
    header('Content-Type: application/json');
    $data = json_decode(file_get_contents('php://input'), true);
    $action = $data['action'] ?? '';

    if ($action === 'save') {
        $d = $data;
        $tags_json = !empty($d['tags']) ? json_encode(array_filter(array_map('trim', explode(',', $d['tags'])))) : null;
        $outcomes_json = !empty($d['outcomes']) ? json_encode(array_filter(array_map('trim', explode("\n", $d['outcomes'])))) : null;
        $reqs_json = !empty($d['requirements']) ? json_encode(array_filter(array_map('trim', explode("\n", $d['requirements'])))) : null;
        $is_free = !empty($d['is_free']) ? 1 : 0;
        if (!empty($d['id'])) {
            db()->execute("UPDATE courses SET title=?,description=?,full_description=?,course_type=?,category=?,level=?,status=?,duration_hours=?,price=?,discount_price=?,is_free=?,instructor_name=?,instructor_bio=?,certificate_offered=?,max_students=?,tags=?,requirements=?,outcomes=? WHERE id=?",
                [$d['title'],$d['description'],$d['full_description'],$d['course_type'],$d['category'],$d['level'],$d['status'],$d['duration_hours']?:null,$d['price']??0,$d['discount_price']?:null,$is_free,$d['instructor_name'],$d['instructor_bio'],$d['certificate_offered']??1,$d['max_students']?:null,$tags_json,$reqs_json,$outcomes_json,$d['id']]);
        } else {
            $slug = generateSlug($d['title']) . '-' . time();
            db()->insert("INSERT INTO courses (title,slug,description,full_description,course_type,category,level,status,duration_hours,price,discount_price,is_free,instructor_name,instructor_bio,certificate_offered,max_students,tags,requirements,outcomes) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)",
                [$d['title'],$slug,$d['description'],$d['full_description'],$d['course_type'],$d['category'],$d['level'],$d['status']??'draft',$d['duration_hours']?:null,$d['price']??0,$d['discount_price']?:null,$is_free,$d['instructor_name'],$d['instructor_bio'],$d['certificate_offered']??1,$d['max_students']?:null,$tags_json,$reqs_json,$outcomes_json]);
        }
        echo json_encode(['success'=>true,'message'=>'Course saved']);
    } elseif ($action === 'delete') {
        db()->execute("DELETE FROM courses WHERE id=?",[$data['id']]);
        echo json_encode(['success'=>true,'message'=>'Course deleted']);
    } elseif ($action === 'toggle_status') {
        $new = $data['current'] === 'published' ? 'draft' : 'published';
        db()->execute("UPDATE courses SET status=? WHERE id=?",[$new,$data['id']]);
        echo json_encode(['success'=>true,'message'=>'Status updated to '.$new]);
    } elseif ($action === 'get_enrollments') {
        $enrollments = db()->fetchAll("SELECT * FROM course_enrollments WHERE course_id=? ORDER BY enrollment_date DESC",[$data['course_id']]);
        echo json_encode(['success'=>true,'enrollments'=>$enrollments]);
    } elseif ($action === 'save_module') {
        $d = $data;
        if (!empty($d['id'])) {
            db()->execute("UPDATE course_modules SET title=?,description=?,sort_order=? WHERE id=?",[$d['title'],$d['description'],$d['sort_order']??0,$d['id']]);
        } else {
            db()->insert("INSERT INTO course_modules (course_id,title,description,sort_order) VALUES (?,?,?,?)",[$d['course_id'],$d['title'],$d['description'],$d['sort_order']??0]);
        }
        echo json_encode(['success'=>true,'message'=>'Module saved']);
    } elseif ($action === 'get_modules') {
        $modules = db()->fetchAll("SELECT m.*,(SELECT COUNT(*) FROM course_lessons WHERE module_id=m.id) as lesson_count FROM course_modules m WHERE m.course_id=? ORDER BY m.sort_order",[$data['course_id']]);
        echo json_encode(['success'=>true,'modules'=>$modules]);
    } elseif ($action === 'delete_module') {
        db()->execute("DELETE FROM course_modules WHERE id=?",[$data['id']]);
        echo json_encode(['success'=>true]);
    }
    exit;
}

$search   = sanitize($_GET['search'] ?? '');
$level    = sanitize($_GET['level'] ?? '');
$status   = sanitize($_GET['status'] ?? '');
$where    = ["1=1"]; $params = [];
if ($search) { $where[] = "title LIKE ?"; $params[] = "%$search%"; }
if ($level)  { $where[] = "level = ?"; $params[] = $level; }
if ($status) { $where[] = "status = ?"; $params[] = $status; }
$whereStr = implode(' AND ', $where);
$courses = db()->fetchAll("SELECT c.*,(SELECT COUNT(*) FROM course_enrollments WHERE course_id=c.id) as student_count FROM courses c WHERE $whereStr ORDER BY c.created_at DESC", $params);

include __DIR__ . '/../includes/header.php';
?>
<style>
.course-card{background:var(--bg-card);border:1px solid var(--border-color);border-radius:14px;padding:20px;transition:.2s;position:relative;overflow:hidden;}
.course-card:hover{border-color:var(--border-active);transform:translateY(-2px);box-shadow:var(--shadow-gold);}
.course-card::before{content:'';position:absolute;top:0;left:0;right:0;height:3px;}
.level-beginner::before{background:linear-gradient(90deg,#2ECC71,#27AE60);}
.level-intermediate::before{background:linear-gradient(90deg,#F39C12,#D68910);}
.level-advanced::before{background:linear-gradient(90deg,#E74C3C,#C0392B);}
.level-expert::before{background:linear-gradient(90deg,#9B59B6,#7D3C98);}
.level-badge{padding:3px 9px;border-radius:20px;font-size:.72rem;font-weight:600;}
.lb-beginner{background:rgba(46,204,113,.15);color:#2ECC71;}
.lb-intermediate{background:rgba(243,156,18,.15);color:#F39C12;}
.lb-advanced{background:rgba(231,76,60,.15);color:#E74C3C;}
.lb-expert{background:rgba(155,89,182,.15);color:#9B59B6;}
.type-badge{padding:3px 9px;border-radius:20px;font-size:.72rem;font-weight:600;}
.tb-online{background:rgba(52,152,219,.15);color:#3498DB;}
.tb-offline{background:rgba(241,196,15,.15);color:#F1C40F;}
.tb-hybrid{background:rgba(201,168,76,.15);color:var(--gold-primary);}
.progress-bar-wrap{height:6px;background:var(--bg-elevated);border-radius:3px;overflow:hidden;}
.progress-bar-fill{height:100%;background:var(--gold-gradient);border-radius:3px;transition:.3s;}
.enroll-row{display:flex;justify-content:space-between;align-items:center;padding:10px 0;border-bottom:1px solid var(--border-color);}
.enroll-row:last-child{border-bottom:none;}
</style>

<div class="page-header fade-in">
  <div class="page-header-left">
    <h1><i class="fa-solid fa-graduation-cap" style="color:var(--gold-primary);margin-right:10px;"></i>Course Management</h1>
    <p>Online, offline & hybrid dental education courses — <?= count($courses) ?> courses</p>
  </div>
  <button class="btn btn-gold" onclick="openCourseModal()"><i class="fa-solid fa-plus"></i> Add Course</button>
</div>

<div class="filter-bar fade-in" style="flex-wrap:wrap;">
  <div class="search-wrapper" style="flex:1;min-width:180px;">
    <i class="fa-solid fa-magnifying-glass"></i>
    <input type="text" class="search-input" id="searchInput" placeholder="Search courses..." value="<?= $search ?>">
  </div>
  <select class="form-control" id="levelFilter" style="max-width:160px;">
    <option value="">All Levels</option>
    <?php foreach(['beginner','intermediate','advanced','expert'] as $l): ?><option value="<?= $l ?>" <?= $level===$l?'selected':'' ?>><?= ucfirst($l) ?></option><?php endforeach; ?>
  </select>
  <select class="form-control" id="statusFilter" style="max-width:150px;">
    <option value="">All Status</option>
    <?php foreach(['draft','published','archived'] as $s): ?><option value="<?= $s ?>" <?= $status===$s?'selected':'' ?>><?= ucfirst($s) ?></option><?php endforeach; ?>
  </select>
  <button class="btn btn-ghost btn-sm" onclick="applyFilters()"><i class="fa-solid fa-filter"></i> Filter</button>
  <a href="courses.php" class="btn btn-ghost btn-sm"><i class="fa-solid fa-rotate-left"></i> Reset</a>
</div>

<!-- Courses Grid -->
<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(340px,1fr));gap:18px;" class="fade-in">
  <?php foreach($courses as $c): ?>
  <div class="course-card level-<?= $c['level'] ?>">
    <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:12px;">
      <div style="display:flex;gap:6px;flex-wrap:wrap;">
        <span class="level-badge lb-<?= $c['level'] ?>"><?= ucfirst($c['level']) ?></span>
        <span class="type-badge tb-<?= $c['course_type'] ?>"><?= ucfirst($c['course_type']) ?></span>
        <?php if($c['certificate_offered']): ?><span class="badge badge-info" style="font-size:.68rem;"><i class="fa-solid fa-certificate"></i> Cert</span><?php endif; ?>
      </div>
      <span class="badge badge-<?= ['draft'=>'secondary','published'=>'success','archived'=>'warning'][$c['status']] ?>"><?= ucfirst($c['status']) ?></span>
    </div>
    <h3 style="font-family:'Playfair Display',serif;font-size:1rem;margin-bottom:6px;line-height:1.4;"><?= htmlspecialchars($c['title']) ?></h3>
    <?php if($c['category']): ?><div style="font-size:.75rem;color:var(--gold-primary);margin-bottom:8px;"><?= htmlspecialchars($c['category']) ?></div><?php endif; ?>
    <?php if($c['description']): ?><p style="font-size:.82rem;color:var(--text-secondary);margin-bottom:10px;line-height:1.5;"><?= htmlspecialchars(substr($c['description'],0,100)) ?><?= strlen($c['description'])>100?'...':'' ?></p><?php endif; ?>
    <div style="display:flex;gap:14px;font-size:.78rem;color:var(--text-muted);margin-bottom:12px;">
      <?php if($c['duration_hours']): ?><span><i class="fa-regular fa-clock" style="margin-right:4px;"></i><?= $c['duration_hours'] ?> hrs</span><?php endif; ?>
      <?php if($c['total_lessons']): ?><span><i class="fa-solid fa-play-circle" style="margin-right:4px;"></i><?= $c['total_lessons'] ?> lessons</span><?php endif; ?>
      <?php if($c['instructor_name']): ?><span><i class="fa-solid fa-user-tie" style="margin-right:4px;"></i><?= htmlspecialchars($c['instructor_name']) ?></span><?php endif; ?>
    </div>
    <div style="display:flex;justify-content:space-between;align-items:center;padding-top:12px;border-top:1px solid var(--border-color);">
      <div>
        <div style="font-weight:700;font-size:1rem;color:var(--gold-primary);"><?= $c['is_free'] ? '<span style="color:var(--success);">FREE</span>' : formatCurrency($c['price']) ?></div>
        <?php if($c['discount_price'] && !$c['is_free']): ?><div style="font-size:.75rem;color:var(--text-muted);text-decoration:line-through;"><?= formatCurrency($c['price']) ?></div><?php endif; ?>
        <div style="font-size:.75rem;color:var(--text-muted);margin-top:2px;"><i class="fa-solid fa-users" style="margin-right:3px;"></i><?= $c['student_count'] ?> enrolled</div>
      </div>
      <div style="display:flex;gap:5px;">
        <button class="btn btn-ghost btn-sm btn-icon" title="Enrollments" onclick="viewEnrollments(<?= $c['id'] ?>,'<?= addslashes(htmlspecialchars($c['title'])) ?>')"><i class="fa-solid fa-users"></i></button>
        <button class="btn btn-ghost btn-sm btn-icon" title="Modules" onclick="manageModules(<?= $c['id'] ?>,'<?= addslashes(htmlspecialchars($c['title'])) ?>')"><i class="fa-solid fa-list"></i></button>
        <button class="btn btn-ghost btn-sm btn-icon" title="Edit" onclick='openCourseModal(<?= json_encode($c) ?>)'><i class="fa-solid fa-pen"></i></button>
        <button class="btn btn-ghost btn-sm btn-icon" title="<?= $c['status']==='published'?'Unpublish':'Publish' ?>" onclick="toggleCourseStatus(<?= $c['id'] ?>,'<?= $c['status'] ?>')">
          <i class="fa-solid fa-<?= $c['status']==='published'?'eye-slash':'rocket' ?>" style="color:<?= $c['status']==='published'?'var(--warning)':'var(--success)' ?>;"></i>
        </button>
        <button class="btn btn-ghost btn-sm btn-icon" title="Delete" onclick="deleteCourse(<?= $c['id'] ?>)"><i class="fa-solid fa-trash" style="color:var(--danger);"></i></button>
      </div>
    </div>
  </div>
  <?php endforeach; ?>
  <?php if(empty($courses)): ?>
  <div class="card" style="padding:40px;text-align:center;grid-column:1/-1;">
    <i class="fa-solid fa-graduation-cap" style="font-size:2.5rem;color:var(--text-muted);margin-bottom:12px;"></i>
    <p style="color:var(--text-muted);">No courses yet. <a href="#" onclick="openCourseModal()" style="color:var(--gold-primary);">Create your first course</a></p>
  </div>
  <?php endif; ?>
</div>

<!-- COURSE MODAL -->
<div class="modal-overlay" id="courseModal" style="display:none;" onclick="if(event.target===this)closeModal('courseModal')">
  <div class="modal-box" style="max-width:720px;width:96vw;">
    <div class="modal-head"><h2 id="courseModalTitle">Add Course</h2><button class="close-btn" onclick="closeModal('courseModal')"><i class="fa-solid fa-xmark"></i></button></div>
    <div class="modal-body" style="max-height:65vh;overflow-y:auto;">
      <input type="hidden" id="course_id">
      <div class="form-group"><label class="form-label">Course Title *</label><input type="text" class="form-control" id="course_title" placeholder="e.g. Advanced Implantology Masterclass"></div>
      <div class="form-row">
        <div class="form-group"><label class="form-label">Type</label>
          <select class="form-control" id="course_type"><option value="online">Online</option><option value="offline">Offline</option><option value="hybrid">Hybrid</option></select>
        </div>
        <div class="form-group"><label class="form-label">Category</label><input type="text" class="form-control" id="course_category" placeholder="e.g. Implantology"></div>
      </div>
      <div class="form-row">
        <div class="form-group"><label class="form-label">Level</label>
          <select class="form-control" id="course_level">
            <?php foreach(['beginner','intermediate','advanced','expert'] as $l): ?><option value="<?= $l ?>"><?= ucfirst($l) ?></option><?php endforeach; ?>
          </select>
        </div>
        <div class="form-group"><label class="form-label">Status</label>
          <select class="form-control" id="course_status">
            <?php foreach(['draft','published','archived'] as $s): ?><option value="<?= $s ?>"><?= ucfirst($s) ?></option><?php endforeach; ?>
          </select>
        </div>
      </div>
      <div class="form-group"><label class="form-label">Short Description</label><textarea class="form-control" id="course_desc" rows="2" placeholder="Brief course summary..."></textarea></div>
      <div class="form-group"><label class="form-label">Full Description</label><textarea class="form-control" id="course_full_desc" rows="3" placeholder="Detailed course content, topics covered..."></textarea></div>
      <div class="form-row-3">
        <div class="form-group"><label class="form-label">Duration (hours)</label><input type="number" class="form-control" id="course_duration" placeholder="20"></div>
        <div class="form-group"><label class="form-label">Total Lessons</label><input type="number" class="form-control" id="course_lessons" placeholder="12"></div>
        <div class="form-group"><label class="form-label">Max Students</label><input type="number" class="form-control" id="course_max" placeholder="Unlimited"></div>
      </div>
      <div class="form-row">
        <div class="form-group">
          <label class="form-label">Price (₹)</label>
          <input type="number" class="form-control" id="course_price" placeholder="0">
          <label style="display:flex;align-items:center;gap:6px;margin-top:6px;cursor:pointer;"><input type="checkbox" id="course_free" style="width:14px;height:14px;accent-color:var(--gold-primary);" onchange="document.getElementById('course_price').disabled=this.checked;if(this.checked)document.getElementById('course_price').value=0;"><span style="font-size:.8rem;color:var(--text-secondary);">Free Course</span></label>
        </div>
        <div class="form-group"><label class="form-label">Discount Price (₹)</label><input type="number" class="form-control" id="course_discount" placeholder="Optional"></div>
      </div>
      <div class="form-row">
        <div class="form-group"><label class="form-label">Instructor Name</label><input type="text" class="form-control" id="course_instructor" placeholder="Dr. Name"></div>
        <div class="form-group" style="display:flex;align-items:flex-end;padding-bottom:4px;">
          <label style="display:flex;align-items:center;gap:8px;cursor:pointer;"><input type="checkbox" id="course_cert" style="width:15px;height:15px;accent-color:var(--gold-primary);"><span class="form-label" style="margin:0;">Certificate Offered</span></label>
        </div>
      </div>
      <div class="form-group"><label class="form-label">Instructor Bio</label><textarea class="form-control" id="course_instructor_bio" rows="2" placeholder="Brief instructor background..."></textarea></div>
      <div class="form-group"><label class="form-label">Learning Outcomes <small class="text-muted">(one per line)</small></label><textarea class="form-control" id="course_outcomes" rows="3" placeholder="Master implant placement techniques&#10;Read and interpret CBCT scans&#10;Handle complications effectively"></textarea></div>
      <div class="form-group"><label class="form-label">Requirements <small class="text-muted">(one per line)</small></label><textarea class="form-control" id="course_requirements" rows="2" placeholder="BDS degree&#10;Basic implantology knowledge"></textarea></div>
      <div class="form-group"><label class="form-label">Tags <small class="text-muted">(comma-separated)</small></label><input type="text" class="form-control" id="course_tags" placeholder="implants, surgery, advanced"></div>
    </div>
    <div class="modal-foot">
      <button class="btn btn-ghost" onclick="closeModal('courseModal')">Cancel</button>
      <button class="btn btn-gold" onclick="saveCourse()"><i class="fa-solid fa-floppy-disk"></i> Save Course</button>
    </div>
  </div>
</div>

<!-- ENROLLMENTS MODAL -->
<div class="modal-overlay" id="enrollModal" style="display:none;" onclick="if(event.target===this)closeModal('enrollModal')">
  <div class="modal-box" style="max-width:680px;">
    <div class="modal-head"><h2 id="enrollTitle">Enrollments</h2><button class="close-btn" onclick="closeModal('enrollModal')"><i class="fa-solid fa-xmark"></i></button></div>
    <div class="modal-body" id="enrollBody" style="max-height:60vh;overflow-y:auto;"></div>
  </div>
</div>

<!-- MODULES MODAL -->
<div class="modal-overlay" id="modulesModal" style="display:none;" onclick="if(event.target===this)closeModal('modulesModal')">
  <div class="modal-box" style="max-width:600px;">
    <div class="modal-head"><h2 id="modulesTitle">Course Modules</h2><button class="close-btn" onclick="closeModal('modulesModal')"><i class="fa-solid fa-xmark"></i></button></div>
    <div class="modal-body">
      <div id="modulesBody" style="max-height:50vh;overflow-y:auto;margin-bottom:14px;"></div>
      <div style="background:var(--bg-elevated);border-radius:10px;padding:14px;">
        <div style="font-weight:600;font-size:.85rem;margin-bottom:10px;">Add Module</div>
        <input type="hidden" id="new_module_course_id">
        <div class="form-group"><input type="text" class="form-control" id="new_module_title" placeholder="Module title..."></div>
        <div class="form-group"><textarea class="form-control" id="new_module_desc" rows="2" placeholder="Description (optional)..."></textarea></div>
        <button class="btn btn-gold btn-sm" onclick="addModule()"><i class="fa-solid fa-plus"></i> Add Module</button>
      </div>
    </div>
  </div>
</div>

<script>
function applyFilters(){window.location.href=`courses.php?search=${encodeURIComponent(document.getElementById('searchInput').value)}&level=${document.getElementById('levelFilter').value}&status=${document.getElementById('statusFilter').value}`;}

function openCourseModal(c=null){
  document.getElementById('course_id').value=c?.id||'';
  document.getElementById('course_title').value=c?.title||'';
  document.getElementById('course_type').value=c?.course_type||'online';
  document.getElementById('course_category').value=c?.category||'';
  document.getElementById('course_level').value=c?.level||'beginner';
  document.getElementById('course_status').value=c?.status||'draft';
  document.getElementById('course_desc').value=c?.description||'';
  document.getElementById('course_full_desc').value=c?.full_description||'';
  document.getElementById('course_duration').value=c?.duration_hours||'';
  document.getElementById('course_lessons').value=c?.total_lessons||'';
  document.getElementById('course_max').value=c?.max_students||'';
  document.getElementById('course_price').value=c?.price||'';
  document.getElementById('course_discount').value=c?.discount_price||'';
  document.getElementById('course_instructor').value=c?.instructor_name||'';
  document.getElementById('course_instructor_bio').value=c?.instructor_bio||'';
  document.getElementById('course_cert').checked=!!(c?.certificate_offered??1);
  const isFree=!!(c?.is_free);
  document.getElementById('course_free').checked=isFree;
  document.getElementById('course_price').disabled=isFree;
  try{const outcomes=c?.outcomes?JSON.parse(c.outcomes):[];document.getElementById('course_outcomes').value=outcomes.join('\n');}catch(e){document.getElementById('course_outcomes').value='';}
  try{const reqs=c?.requirements?JSON.parse(c.requirements):[];document.getElementById('course_requirements').value=reqs.join('\n');}catch(e){document.getElementById('course_requirements').value='';}
  try{const tags=c?.tags?JSON.parse(c.tags):[];document.getElementById('course_tags').value=tags.join(', ');}catch(e){document.getElementById('course_tags').value='';}
  document.getElementById('courseModalTitle').textContent=c?'Edit Course':'Add Course';
  openModal('courseModal');
}

async function saveCourse(){
  const title=document.getElementById('course_title').value.trim();
  if(!title){showToast('Course title is required','warning');return;}
  const payload={action:'save',id:document.getElementById('course_id').value,title,
    course_type:document.getElementById('course_type').value,category:document.getElementById('course_category').value,
    level:document.getElementById('course_level').value,status:document.getElementById('course_status').value,
    description:document.getElementById('course_desc').value,full_description:document.getElementById('course_full_desc').value,
    duration_hours:document.getElementById('course_duration').value,total_lessons:document.getElementById('course_lessons').value,
    max_students:document.getElementById('course_max').value,price:document.getElementById('course_price').value||0,
    discount_price:document.getElementById('course_discount').value,is_free:document.getElementById('course_free').checked?1:0,
    instructor_name:document.getElementById('course_instructor').value,instructor_bio:document.getElementById('course_instructor_bio').value,
    certificate_offered:document.getElementById('course_cert').checked?1:0,
    outcomes:document.getElementById('course_outcomes').value,requirements:document.getElementById('course_requirements').value,
    tags:document.getElementById('course_tags').value};
  const res=await fetch('courses.php',{method:'POST',headers:{'Content-Type':'application/json','X-Requested-With':'XMLHttpRequest'},body:JSON.stringify(payload)});
  const r=await res.json();if(r.success){showToast(r.message,'success');closeModal('courseModal');setTimeout(()=>location.reload(),700);}
  else showToast(r.message||'Error','danger');
}

async function toggleCourseStatus(id,current){
  const res=await fetch('courses.php',{method:'POST',headers:{'Content-Type':'application/json','X-Requested-With':'XMLHttpRequest'},body:JSON.stringify({action:'toggle_status',id,current})});
  const r=await res.json();if(r.success){showToast(r.message,'success');setTimeout(()=>location.reload(),600);}
}

function deleteCourse(id){
  showConfirm('Delete Course','Remove this course and all enrollments?',async()=>{
    await fetch('courses.php',{method:'POST',headers:{'Content-Type':'application/json','X-Requested-With':'XMLHttpRequest'},body:JSON.stringify({action:'delete',id})});
    showToast('Course deleted','success');setTimeout(()=>location.reload(),700);
  });
}

async function viewEnrollments(id,name){
  document.getElementById('enrollTitle').textContent='Enrollments — '+name;
  const res=await fetch('courses.php',{method:'POST',headers:{'Content-Type':'application/json','X-Requested-With':'XMLHttpRequest'},body:JSON.stringify({action:'get_enrollments',course_id:id})});
  const data=await res.json();
  const body=document.getElementById('enrollBody');
  if(!data.enrollments?.length){body.innerHTML='<div class="empty-state"><i class="fa-solid fa-users-slash"></i><p>No enrollments yet</p></div>';return;}
  body.innerHTML=`<div style="margin-bottom:12px;color:var(--text-muted);font-size:.82rem;">${data.enrollments.length} students enrolled</div>`+
  data.enrollments.map(e=>`
    <div class="enroll-row">
      <div>
        <div style="font-weight:600;font-size:.88rem;">${e.student_name}</div>
        <div style="font-size:.75rem;color:var(--text-muted);">${e.student_email}</div>
      </div>
      <div style="display:flex;gap:8px;align-items:center;">
        <div style="text-align:right;">
          <div style="font-size:.75rem;color:var(--text-muted);">Progress</div>
          <div style="font-size:.85rem;font-weight:600;color:var(--gold-primary);">${e.progress_percent}%</div>
        </div>
        <span class="badge badge-${e.payment_status==='paid'?'success':e.payment_status==='free'?'info':'warning'}">${e.payment_status}</span>
        ${e.certificate_issued?'<span class="badge badge-gold"><i class="fa-solid fa-certificate"></i> Cert</span>':''}
      </div>
    </div>`).join('');
  openModal('enrollModal');
}

let currentModuleCourseId=null;
async function manageModules(id,name){
  currentModuleCourseId=id;
  document.getElementById('modulesTitle').textContent='Modules — '+name;
  document.getElementById('new_module_course_id').value=id;
  await loadModules();
  openModal('modulesModal');
}
async function loadModules(){
  const res=await fetch('courses.php',{method:'POST',headers:{'Content-Type':'application/json','X-Requested-With':'XMLHttpRequest'},body:JSON.stringify({action:'get_modules',course_id:currentModuleCourseId})});
  const data=await res.json();
  const body=document.getElementById('modulesBody');
  if(!data.modules?.length){body.innerHTML='<p style="color:var(--text-muted);text-align:center;padding:16px;">No modules yet. Add one below.</p>';return;}
  body.innerHTML=data.modules.map((m,i)=>`
    <div style="background:var(--bg-elevated);border-radius:10px;padding:12px;margin-bottom:8px;display:flex;justify-content:space-between;align-items:center;">
      <div><div style="font-weight:600;font-size:.88rem;">${i+1}. ${m.title}</div><div style="font-size:.75rem;color:var(--text-muted);">${m.lesson_count} lessons</div></div>
      <button class="btn btn-ghost btn-sm btn-icon" onclick="deleteModule(${m.id})"><i class="fa-solid fa-trash" style="color:var(--danger);"></i></button>
    </div>`).join('');
}
async function addModule(){
  const title=document.getElementById('new_module_title').value.trim();
  if(!title){showToast('Module title required','warning');return;}
  const res=await fetch('courses.php',{method:'POST',headers:{'Content-Type':'application/json','X-Requested-With':'XMLHttpRequest'},body:JSON.stringify({action:'save_module',course_id:currentModuleCourseId,title,description:document.getElementById('new_module_desc').value})});
  const r=await res.json();if(r.success){document.getElementById('new_module_title').value='';document.getElementById('new_module_desc').value='';showToast('Module added','success');await loadModules();}
}
async function deleteModule(id){
  if(!confirm('Delete this module?'))return;
  await fetch('courses.php',{method:'POST',headers:{'Content-Type':'application/json','X-Requested-With':'XMLHttpRequest'},body:JSON.stringify({action:'delete_module',id})});
  await loadModules();showToast('Module deleted','success');
}
</script>
<?php include __DIR__ . '/../includes/footer.php'; ?>
