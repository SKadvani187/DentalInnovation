<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
$page_title = 'Product Q&A';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SERVER['HTTP_X_REQUESTED_WITH'])) {
    header('Content-Type: application/json');
    $d = json_decode(file_get_contents('php://input'), true);
    $action = $d['action'] ?? '';
    if ($action === 'answer') {
        $ans = trim((string)($d['answer'] ?? ''));
        if ($ans === '') { echo json_encode(['success'=>false,'message'=>'Answer cannot be empty']); exit; }
        // Answering also approves + publishes the question.
        db()->execute("UPDATE product_questions SET answer=?, is_answered=1, is_approved=1, answered_at=NOW() WHERE id=?", [$ans, $d['id']]);
        echo json_encode(['success'=>true,'message'=>'Answer published']);
    } elseif ($action === 'approve') {
        db()->execute("UPDATE product_questions SET is_approved=? WHERE id=?", [(int)$d['approved'], $d['id']]);
        echo json_encode(['success'=>true,'message'=>$d['approved'] ? 'Published' : 'Hidden']);
    } elseif ($action === 'delete') {
        db()->execute("DELETE FROM product_questions WHERE id=?", [$d['id']]);
        echo json_encode(['success'=>true,'message'=>'Question deleted']);
    }
    exit;
}

$search   = sanitize($_GET['search'] ?? '');
$status   = $_GET['status'] ?? '';   // '' all, 'pending', 'answered'
$page     = max(1,(int)($_GET['page'] ?? 1));
$per_page = 15; $offset = ($page-1)*$per_page;

$where = ["1=1"]; $params = [];
if ($search) { $where[] = "(q.question LIKE ? OR q.asker_name LIKE ? OR p.name LIKE ?)"; $params = array_merge($params, ["%$search%","%$search%","%$search%"]); }
if ($status === 'pending')  { $where[] = "q.is_answered=0"; }
if ($status === 'answered') { $where[] = "q.is_answered=1"; }
$whereStr = implode(' AND ', $where);

$total = db()->fetchOne("SELECT COUNT(*) c FROM product_questions q LEFT JOIN products p ON q.product_id=p.id WHERE $whereStr", $params)['c'];
$pages = ceil($total/$per_page);
$questions = db()->fetchAll("SELECT q.*, p.name AS product_name FROM product_questions q LEFT JOIN products p ON q.product_id=p.id WHERE $whereStr ORDER BY q.is_answered ASC, q.created_at DESC LIMIT $per_page OFFSET $offset", $params);
$stats = db()->fetchOne("SELECT COUNT(*) total, SUM(is_answered=0) pending, SUM(is_answered=1) answered FROM product_questions");

include __DIR__ . '/../includes/header.php';
?>
<div class="page-header fade-in">
  <div class="page-header-left">
    <h1><i class="fa-regular fa-circle-question" style="color:var(--gold-primary);margin-right:10px;"></i>Product Q&amp;A</h1>
    <p>Answer customer questions — <?= number_format($total) ?> total</p>
  </div>
</div>

<!-- Stats -->
<div style="display:grid;grid-template-columns:repeat(3,1fr);gap:14px;margin-bottom:20px;" class="fade-in">
  <?php $sc=[['Total Questions',$stats['total']??0,'fa-circle-question','#C9A84C'],['Pending',$stats['pending']??0,'fa-clock','#F39C12'],['Answered',$stats['answered']??0,'fa-circle-check','#2ECC71']];
  foreach($sc as [$label,$val,$icon,$color]): ?>
  <div class="card" style="padding:16px 20px;display:flex;align-items:center;gap:14px;">
    <div style="width:40px;height:40px;border-radius:10px;background:<?= $color ?>1a;display:grid;place-items:center;flex-shrink:0;"><i class="fa-solid <?= $icon ?>" style="color:<?= $color ?>;"></i></div>
    <div><div class="stat-value" style="font-size:1.4rem;"><?= $val ?></div><div class="stat-label"><?= $label ?></div></div>
  </div>
  <?php endforeach; ?>
</div>

<!-- Filters -->
<div class="filter-bar fade-in" style="flex-wrap:wrap;">
  <div class="search-wrapper" style="flex:1;min-width:180px;"><i class="fa-solid fa-magnifying-glass"></i><input type="text" class="search-input" id="searchInput" placeholder="Search questions..." value="<?= htmlspecialchars($search) ?>"></div>
  <select class="form-control" id="statusFilter" style="max-width:160px;">
    <option value="">All</option>
    <option value="pending" <?= $status==='pending'?'selected':'' ?>>Pending</option>
    <option value="answered" <?= $status==='answered'?'selected':'' ?>>Answered</option>
  </select>
  <button class="btn btn-ghost btn-sm" onclick="applyFilters()"><i class="fa-solid fa-filter"></i> Filter</button>
  <a href="questions.php" class="btn btn-ghost btn-sm"><i class="fa-solid fa-rotate-left"></i> Reset</a>
</div>

<div class="card fade-in">
  <div class="table-responsive">
    <table>
      <thead><tr><th>Product</th><th>Asker</th><th>Question</th><th>Answer</th><th>Date</th><th>Status</th><th>Actions</th></tr></thead>
      <tbody>
        <?php foreach($questions as $q): ?>
        <tr id="q-row-<?= $q['id'] ?>">
          <td style="font-size:.84rem;font-weight:600;max-width:150px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"><?= htmlspecialchars($q['product_name'] ?? 'Unknown') ?></td>
          <td style="font-size:.82rem;"><?= htmlspecialchars($q['asker_name'] ?: 'Customer') ?><?php if($q['asker_email']): ?><div style="font-size:.72rem;color:var(--text-muted);"><?= htmlspecialchars($q['asker_email']) ?></div><?php endif; ?></td>
          <td style="max-width:240px;font-size:.83rem;"><?= htmlspecialchars($q['question']) ?></td>
          <td style="max-width:240px;font-size:.82rem;color:var(--text-secondary);"><?= $q['answer'] ? htmlspecialchars($q['answer']) : '<span class="text-muted">—</span>' ?></td>
          <td style="font-size:.78rem;color:var(--text-muted);white-space:nowrap;"><?= date('d M Y', strtotime($q['created_at'])) ?></td>
          <td><span class="badge badge-<?= $q['is_answered'] ? 'success' : 'warning' ?>"><?= $q['is_answered'] ? 'Answered' : 'Pending' ?></span></td>
          <td>
            <div style="display:flex;gap:4px;">
              <button class="btn btn-ghost btn-sm btn-icon" onclick='openAnswer(<?= json_encode($q) ?>)' title="Answer"><i class="fa-solid fa-reply" style="color:var(--gold-primary);"></i></button>
              <?php if($q['is_answered']): ?>
              <button class="btn btn-ghost btn-sm btn-icon" onclick="approveQ(<?= $q['id'] ?>,<?= $q['is_approved']?0:1 ?>)" title="<?= $q['is_approved']?'Hide':'Publish' ?>"><i class="fa-solid fa-<?= $q['is_approved']?'eye-slash':'eye' ?>" style="color:<?= $q['is_approved']?'var(--warning)':'var(--success)' ?>;"></i></button>
              <?php endif; ?>
              <button class="btn btn-ghost btn-sm btn-icon" onclick="deleteQ(<?= $q['id'] ?>)" title="Delete"><i class="fa-solid fa-trash" style="color:var(--danger);"></i></button>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
        <?php if(empty($questions)): ?><tr><td colspan="7"><div class="empty-state"><i class="fa-regular fa-circle-question"></i><p>No questions found</p></div></td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
  <?php if($pages>1): ?>
  <div style="padding:16px 20px;border-top:1px solid var(--border-color);"><div class="pagination"><?php for($i=1;$i<=$pages;$i++): ?><div class="page-item <?= $i==$page?'active':'' ?>" onclick="goPage(<?= $i ?>)"><?= $i ?></div><?php endfor; ?></div></div>
  <?php endif; ?>
</div>

<!-- Answer Modal -->
<div class="modal-overlay" id="answerModal" style="display:none;" onclick="if(event.target===this)closeModal('answerModal')">
  <div class="modal-box" style="max-width:560px;">
    <div class="modal-head"><h2>Answer Question</h2><button class="close-btn" onclick="closeModal('answerModal')"><i class="fa-solid fa-xmark"></i></button></div>
    <div class="modal-body">
      <input type="hidden" id="ans_id">
      <div style="background:var(--bg-elevated);padding:12px;border-radius:8px;margin-bottom:14px;">
        <div style="font-size:.75rem;color:var(--text-muted);margin-bottom:4px;" id="ans_meta"></div>
        <div style="font-weight:600;" id="ans_question"></div>
      </div>
      <div class="form-group"><label class="form-label">Your Answer *</label><textarea class="form-control" id="ans_text" rows="5" placeholder="Type the official answer..."></textarea></div>
      <small class="text-muted" style="font-size:.73rem;">Saving an answer publishes it on the product Q&amp;A page.</small>
    </div>
    <div class="modal-foot">
      <button class="btn btn-ghost" onclick="closeModal('answerModal')">Cancel</button>
      <button class="btn btn-gold" onclick="saveAnswer()"><i class="fa-solid fa-paper-plane"></i> Publish Answer</button>
    </div>
  </div>
</div>

<script>
function applyFilters(){window.location.href=`questions.php?search=${encodeURIComponent(document.getElementById('searchInput').value)}&status=${document.getElementById('statusFilter').value}`;}
function goPage(p){window.location.href=`questions.php?page=${p}`;}
async function post(payload){const res=await fetch('questions.php',{method:'POST',headers:{'Content-Type':'application/json','X-Requested-With':'XMLHttpRequest'},body:JSON.stringify(payload)});return res.json();}

function openAnswer(q){
  document.getElementById('ans_id').value=q.id;
  document.getElementById('ans_meta').textContent=(q.asker_name||'Customer')+' • '+(q.product_name||'');
  document.getElementById('ans_question').textContent=q.question;
  document.getElementById('ans_text').value=q.answer||'';
  openModal('answerModal');
}
async function saveAnswer(){
  const answer=document.getElementById('ans_text').value.trim();
  if(!answer){showToast('Answer cannot be empty','warning');return;}
  const r=await post({action:'answer',id:document.getElementById('ans_id').value,answer});
  if(r.success){showToast(r.message,'success');closeModal('answerModal');setTimeout(()=>location.reload(),600);}
  else showToast(r.message||'Failed','danger');
}
async function approveQ(id,approved){const r=await post({action:'approve',id,approved});if(r.success){showToast(r.message,'success');setTimeout(()=>location.reload(),500);}}
function deleteQ(id){showConfirm('Delete Question','Permanently delete this question?',async()=>{const r=await post({action:'delete',id});if(r.success){showToast(r.message,'success');const row=document.getElementById('q-row-'+id);if(row){row.style.opacity='0';row.style.transition='.3s';setTimeout(()=>row.remove(),300);}}});}
</script>
<?php include __DIR__ . '/../includes/footer.php'; ?>
