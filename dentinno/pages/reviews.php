<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
$page_title = 'Product Reviews';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SERVER['HTTP_X_REQUESTED_WITH'])) {
    header('Content-Type: application/json');
    $data = json_decode(file_get_contents('php://input'), true);
    $action = $data['action'] ?? '';
    if ($action === 'approve') {
        db()->execute("UPDATE product_reviews SET is_approved=? WHERE id=?", [$data['approved'], $data['id']]);
        echo json_encode(['success' => true, 'message' => $data['approved'] ? 'Review approved' : 'Review hidden']);
    } elseif ($action === 'verify') {
        db()->execute("UPDATE product_reviews SET is_verified=1 WHERE id=?", [$data['id']]);
        echo json_encode(['success' => true, 'message' => 'Marked as verified purchase']);
    } elseif ($action === 'delete') {
        db()->execute("DELETE FROM product_reviews WHERE id=?", [$data['id']]);
        echo json_encode(['success' => true, 'message' => 'Review deleted']);
    } elseif ($action === 'bulk_approve') {
        $ids = array_map('intval', $data['ids']);
        $ph  = implode(',', array_fill(0, count($ids), '?'));
        db()->execute("UPDATE product_reviews SET is_approved=1 WHERE id IN ($ph)", $ids);
        echo json_encode(['success' => true, 'message' => count($ids) . ' reviews approved']);
    } elseif ($action === 'bulk_delete') {
        $ids = array_map('intval', $data['ids']);
        $ph  = implode(',', array_fill(0, count($ids), '?'));
        db()->execute("DELETE FROM product_reviews WHERE id IN ($ph)", $ids);
        echo json_encode(['success' => true, 'message' => count($ids) . ' reviews deleted']);
    }
    exit;
}

$search   = sanitize($_GET['search'] ?? '');
$rating   = (int)($_GET['rating'] ?? 0);
$approved = $_GET['approved'] ?? '';
$page     = max(1,(int)($_GET['page'] ?? 1));
$per_page = 15; $offset = ($page-1)*$per_page;

$where = ["1=1"]; $params = [];
if ($search)    { $where[] = "(r.reviewer_name LIKE ? OR r.review LIKE ? OR p.name LIKE ?)"; $params = array_merge($params, ["%$search%","%$search%","%$search%"]); }
if ($rating)    { $where[] = "r.rating=?"; $params[] = $rating; }
if ($approved !== '') { $where[] = "r.is_approved=?"; $params[] = (int)$approved; }
$whereStr = implode(' AND ', $where);

$total   = db()->fetchOne("SELECT COUNT(*) as cnt FROM product_reviews r LEFT JOIN products p ON r.product_id=p.id WHERE $whereStr", $params)['cnt'];
$pages   = ceil($total/$per_page);
$reviews = db()->fetchAll("SELECT r.*,p.name as product_name FROM product_reviews r LEFT JOIN products p ON r.product_id=p.id WHERE $whereStr ORDER BY r.created_at DESC LIMIT $per_page OFFSET $offset", $params);

// Stats
$stats = db()->fetchOne("SELECT COUNT(*) as total, SUM(is_approved=0) as pending, ROUND(AVG(rating),1) as avg_rating, SUM(is_verified=1) as verified FROM product_reviews");

include __DIR__ . '/../includes/header.php';
?>
<style>
.star-display { color:#F0D080;font-size:.88rem; }
.star-empty   { color:var(--text-muted);font-size:.88rem; }
.review-text  { max-width:320px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;font-size:.83rem;color:var(--text-secondary); }
.rating-bar   { display:flex;align-items:center;gap:8px;margin-bottom:5px; }
.rating-bar-fill { height:6px;background:var(--gold-gradient);border-radius:99px;min-width:2px; }
.rating-bar-bg   { flex:1;height:6px;background:var(--bg-elevated);border-radius:99px;overflow:hidden; }
</style>

<div class="page-header fade-in">
  <div class="page-header-left">
    <h1><i class="fa-regular fa-star" style="color:var(--gold-primary);margin-right:10px;"></i>Product Reviews</h1>
    <p>Moderate and manage customer reviews — <?= number_format($total) ?> total</p>
  </div>
  <div style="display:flex;gap:10px;">
    <button class="btn btn-ghost btn-sm" onclick="bulkAction('approve')" id="bulkApproveBtn" style="display:none;"><i class="fa-solid fa-check"></i> Approve Selected</button>
    <button class="btn btn-ghost btn-sm" style="color:var(--danger);display:none;" onclick="bulkAction('delete')" id="bulkDeleteBtn"><i class="fa-solid fa-trash"></i> Delete Selected</button>
  </div>
</div>

<!-- Stats Row -->
<div style="display:grid;grid-template-columns:repeat(4,1fr);gap:14px;margin-bottom:20px;" class="fade-in">
  <?php
  $sc = [
    ['Total Reviews', $stats['total'] ?? 0, 'fa-star', '#C9A84C'],
    ['Pending', $stats['pending'] ?? 0, 'fa-clock', '#F39C12'],
    ['Avg Rating', ($stats['avg_rating'] ?? 0) . ' / 5', 'fa-chart-simple', '#3498DB'],
    ['Verified', $stats['verified'] ?? 0, 'fa-badge-check', '#2ECC71'],
  ];
  foreach($sc as [$label,$val,$icon,$color]): ?>
  <div class="card" style="padding:16px 20px;display:flex;align-items:center;gap:14px;">
    <div style="width:40px;height:40px;border-radius:10px;background:<?= $color ?>1a;display:grid;place-items:center;flex-shrink:0;">
      <i class="fa-solid fa-<?= $icon ?>" style="color:<?= $color ?>;font-size:1rem;"></i>
    </div>
    <div>
      <div class="stat-value" style="font-size:1.4rem;"><?= $val ?></div>
      <div class="stat-label"><?= $label ?></div>
    </div>
  </div>
  <?php endforeach; ?>
</div>

<!-- Filters -->
<div class="filter-bar fade-in" style="flex-wrap:wrap;">
  <div class="search-wrapper" style="flex:1;min-width:180px;">
    <i class="fa-solid fa-magnifying-glass"></i>
    <input type="text" class="search-input" id="searchInput" placeholder="Search reviews..." value="<?= $search ?>">
  </div>
  <select class="form-control" id="ratingFilter" style="max-width:140px;">
    <option value="">All Ratings</option>
    <?php for($i=5;$i>=1;$i--): ?><option value="<?= $i ?>" <?= $rating===$i?'selected':'' ?>><?= str_repeat('★',$i) ?> <?= $i ?> Star</option><?php endfor; ?>
  </select>
  <select class="form-control" id="approvedFilter" style="max-width:150px;">
    <option value="">All Status</option>
    <option value="1" <?= $approved==='1'?'selected':'' ?>>Approved</option>
    <option value="0" <?= $approved==='0'?'selected':'' ?>>Pending</option>
  </select>
  <button class="btn btn-ghost btn-sm" onclick="applyFilters()"><i class="fa-solid fa-filter"></i> Filter</button>
  <a href="reviews.php" class="btn btn-ghost btn-sm"><i class="fa-solid fa-rotate-left"></i> Reset</a>
</div>

<div class="card fade-in">
  <div style="padding:12px 16px;border-bottom:1px solid var(--border-color);display:flex;align-items:center;gap:10px;">
    <input type="checkbox" id="selectAll" style="width:15px;height:15px;accent-color:var(--gold-primary);" onchange="toggleSelectAll(this)">
    <span style="font-size:.78rem;color:var(--text-muted);">Select all on this page</span>
  </div>
  <div class="table-responsive">
    <table>
      <thead>
        <tr><th style="width:32px;"></th><th>Product</th><th>Reviewer</th><th>Rating</th><th>Review</th><th>Date</th><th>Status</th><th>Actions</th></tr>
      </thead>
      <tbody>
        <?php foreach($reviews as $r): ?>
        <tr id="review-row-<?= $r['id'] ?>">
          <td><input type="checkbox" class="review-check" value="<?= $r['id'] ?>" style="width:15px;height:15px;accent-color:var(--gold-primary);" onchange="updateBulkBtns()"></td>
          <td>
            <div style="font-size:.84rem;font-weight:600;max-width:150px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">
              <?= htmlspecialchars($r['product_name'] ?? 'Unknown') ?>
            </div>
          </td>
          <td>
            <div style="font-weight:600;font-size:.84rem;"><?= htmlspecialchars($r['reviewer_name']) ?></div>
            <?php if($r['reviewer_email']): ?><div style="font-size:.72rem;color:var(--text-muted);"><?= htmlspecialchars($r['reviewer_email']) ?></div><?php endif; ?>
            <?php if($r['is_verified']): ?><span class="badge badge-info" style="margin-top:2px;"><i class="fa-solid fa-circle-check"></i> Verified</span><?php endif; ?>
          </td>
          <td>
            <div class="star-display"><?= str_repeat('★',$r['rating']) ?></div>
            <div class="star-empty"><?= str_repeat('★',5-$r['rating']) ?></div>
            <div style="font-size:.72rem;color:var(--text-muted);"><?= $r['rating'] ?>/5</div>
          </td>
          <td>
            <?php if($r['title']): ?><div style="font-weight:600;font-size:.82rem;margin-bottom:2px;"><?= htmlspecialchars($r['title']) ?></div><?php endif; ?>
            <div class="review-text" title="<?= htmlspecialchars($r['review']) ?>"><?= htmlspecialchars($r['review']) ?></div>
          </td>
          <td style="font-size:.78rem;color:var(--text-muted);white-space:nowrap;">
            <?= date('d M Y', strtotime($r['created_at'])) ?><br>
            <?= date('h:i A', strtotime($r['created_at'])) ?>
          </td>
          <td>
            <span class="badge badge-<?= $r['is_approved'] ? 'success' : 'warning' ?>">
              <?= $r['is_approved'] ? 'Approved' : 'Pending' ?>
            </span>
          </td>
          <td>
            <div style="display:flex;gap:4px;">
              <button class="btn btn-ghost btn-sm btn-icon" onclick="approveReview(<?= $r['id'] ?>,<?= $r['is_approved']?0:1 ?>)" title="<?= $r['is_approved']?'Hide':'Approve' ?>">
                <i class="fa-solid fa-<?= $r['is_approved']?'eye-slash':'check' ?>" style="color:<?= $r['is_approved']?'var(--warning)':'var(--success)' ?>;"></i>
              </button>
              <?php if(!$r['is_verified']): ?>
              <button class="btn btn-ghost btn-sm btn-icon" onclick="verifyReview(<?= $r['id'] ?>)" title="Mark Verified">
                <i class="fa-solid fa-badge-check" style="color:#3498DB;"></i>
              </button>
              <?php endif; ?>
              <button class="btn btn-ghost btn-sm btn-icon" onclick="viewReview(<?= $r['id'] ?>)" title="View Full">
                <i class="fa-solid fa-eye" style="color:var(--gold-primary);"></i>
              </button>
              <button class="btn btn-ghost btn-sm btn-icon" onclick="deleteReview(<?= $r['id'] ?>)" title="Delete">
                <i class="fa-solid fa-trash" style="color:var(--danger);"></i>
              </button>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
        <?php if(empty($reviews)): ?>
        <tr><td colspan="8"><div class="empty-state"><i class="fa-regular fa-star"></i><p>No reviews found</p></div></td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
  <?php if($pages > 1): ?>
  <div style="padding:16px 20px;border-top:1px solid var(--border-color);">
    <div class="pagination">
      <?php for($i=1;$i<=$pages;$i++): ?><div class="page-item <?= $i==$page?'active':'' ?>" onclick="goPage(<?= $i ?>)"><?= $i ?></div><?php endfor; ?>
    </div>
  </div>
  <?php endif; ?>
</div>

<!-- View Review Modal -->
<div class="modal-overlay" id="viewModal" style="display:none;" onclick="if(event.target===this)closeModal('viewModal')">
  <div class="modal-box" style="max-width:520px;">
    <div class="modal-head"><h2>Review Details</h2><button class="close-btn" onclick="closeModal('viewModal')"><i class="fa-solid fa-xmark"></i></button></div>
    <div class="modal-body" id="viewModalBody"></div>
  </div>
</div>

<script>
const reviewsData = <?= json_encode(array_column($reviews, null, 'id')) ?>;

function applyFilters(){window.location.href=`reviews.php?search=${encodeURIComponent(document.getElementById('searchInput').value)}&rating=${document.getElementById('ratingFilter').value}&approved=${document.getElementById('approvedFilter').value}`;}
function goPage(p){window.location.href=`reviews.php?page=${p}`;}

function toggleSelectAll(cb){document.querySelectorAll('.review-check').forEach(c=>c.checked=cb.checked);updateBulkBtns();}
function updateBulkBtns(){
  const any=document.querySelectorAll('.review-check:checked').length>0;
  document.getElementById('bulkApproveBtn').style.display=any?'':'none';
  document.getElementById('bulkDeleteBtn').style.display=any?'':'none';
}
function getSelected(){return [...document.querySelectorAll('.review-check:checked')].map(c=>parseInt(c.value));}

async function post(payload){
  const res=await fetch('reviews.php',{method:'POST',headers:{'Content-Type':'application/json','X-Requested-With':'XMLHttpRequest'},body:JSON.stringify(payload)});
  return res.json();
}

async function approveReview(id,approved){
  const r=await post({action:'approve',id,approved});
  if(r.success){showToast(r.message,'success');setTimeout(()=>location.reload(),600);}
}
async function verifyReview(id){
  const r=await post({action:'verify',id});
  if(r.success){showToast(r.message,'success');setTimeout(()=>location.reload(),600);}
}
async function deleteReview(id){
  showConfirm('Delete Review','Permanently delete this review?',async()=>{
    const r=await post({action:'delete',id});
    if(r.success){showToast(r.message,'success');const row=document.getElementById('review-row-'+id);if(row){row.style.opacity='0';row.style.transition='.3s';setTimeout(()=>row.remove(),300);}}
  });
}
async function bulkAction(type){
  const ids=getSelected();if(!ids.length)return;
  if(type==='delete'){
    showConfirm('Delete Reviews',`Delete ${ids.length} selected reviews?`,async()=>{
      const r=await post({action:'bulk_delete',ids});
      if(r.success){showToast(r.message,'success');setTimeout(()=>location.reload(),700);}
    });
  } else {
    const r=await post({action:'bulk_approve',ids});
    if(r.success){showToast(r.message,'success');setTimeout(()=>location.reload(),700);}
  }
}

function viewReview(id){
  const r=reviewsData[id];if(!r)return;
  document.getElementById('viewModalBody').innerHTML=`
    <div style="margin-bottom:14px;">
      <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:12px;">
        <div>
          <div style="font-weight:700;font-size:1rem;">${r.reviewer_name}</div>
          ${r.reviewer_email?`<div style="font-size:.78rem;color:var(--text-muted);">${r.reviewer_email}</div>`:''}
        </div>
        <div style="text-align:right;">
          <div style="color:#F0D080;font-size:1rem;">${'★'.repeat(r.rating)}${'☆'.repeat(5-r.rating)}</div>
          <div style="font-size:.72rem;color:var(--text-muted);">${r.rating}/5 Stars</div>
        </div>
      </div>
      <div style="font-size:.8rem;color:var(--text-muted);margin-bottom:12px;">
        <i class="fa-solid fa-box" style="margin-right:5px;"></i>${r.product_name||'—'}
      </div>
      ${r.title?`<div style="font-weight:600;margin-bottom:6px;">${r.title}</div>`:''}
      <div style="color:var(--text-secondary);font-size:.88rem;line-height:1.7;background:var(--bg-elevated);padding:14px;border-radius:var(--radius-sm);">${r.review}</div>
      <div style="display:flex;gap:8px;margin-top:12px;flex-wrap:wrap;">
        <span class="badge badge-${r.is_approved?'success':'warning'}">${r.is_approved?'Approved':'Pending'}</span>
        ${r.is_verified?'<span class="badge badge-info"><i class="fa-solid fa-circle-check"></i> Verified</span>':''}
        <span style="font-size:.75rem;color:var(--text-muted);margin-left:auto;">${r.created_at}</span>
      </div>
    </div>
    <div class="modal-foot" style="padding:0;margin:0;border:none;justify-content:flex-start;gap:8px;">
      ${!r.is_approved?`<button class="btn btn-gold btn-sm" onclick="approveReview(${r.id},1)"><i class="fa-solid fa-check"></i> Approve</button>`:''}
      <button class="btn btn-ghost btn-sm" style="color:var(--danger);" onclick="deleteReview(${r.id});closeModal('viewModal')"><i class="fa-solid fa-trash"></i> Delete</button>
    </div>`;
  openModal('viewModal');
}
</script>
<?php include __DIR__ . '/../includes/footer.php'; ?>
