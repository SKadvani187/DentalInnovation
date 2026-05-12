/* DentInno CRM — Main JavaScript */

// ── Sidebar Toggle ──
const sidebar = document.getElementById('sidebar');
const sidebarToggle = document.getElementById('sidebarToggle');
if (sidebarToggle) {
    sidebarToggle.addEventListener('click', () => {
        sidebar.classList.toggle('open');
    });
}

// ── Notification Dropdown ──
const notifBtn = document.getElementById('notifBtn');
const notifDropdown = document.getElementById('notifDropdown');
if (notifBtn) {
    notifBtn.addEventListener('click', (e) => {
        e.stopPropagation();
        notifDropdown.classList.toggle('open');
    });
    document.addEventListener('click', () => notifDropdown.classList.remove('open'));
}

// ── Toast Notifications ──
function showToast(message, type = 'success') {
    const container = document.getElementById('toastContainer');
    const icons = { success: 'circle-check', danger: 'circle-xmark', warning: 'triangle-exclamation' };
    const toast = document.createElement('div');
    toast.className = `toast ${type}`;
    toast.innerHTML = `
        <i class="fa-solid fa-${icons[type] || 'circle-check'} toast-icon"></i>
        <span class="toast-text">${message}</span>
    `;
    container.appendChild(toast);
    setTimeout(() => toast.remove(), 3500);
}

// ── Confirm Modal ──
let confirmCallback = null;
function showConfirm(title, msg, callback) {
    document.getElementById('confirmTitle').textContent = title;
    document.getElementById('confirmMsg').textContent = msg;
    document.getElementById('confirmModal').style.display = 'flex';
    confirmCallback = callback;
}
function closeConfirm() {
    document.getElementById('confirmModal').style.display = 'none';
    confirmCallback = null;
}
const confirmOk = document.getElementById('confirmOk');
if (confirmOk) {
    confirmOk.addEventListener('click', () => {
        if (confirmCallback) confirmCallback();
        closeConfirm();
    });
}

// ── Generic Modal Open/Close ──
function openModal(id) { document.getElementById(id).style.display = 'flex'; }
function closeModal(id) { document.getElementById(id).style.display = 'none'; }
document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') {
        document.querySelectorAll('.modal-overlay').forEach(m => m.style.display = 'none');
    }
});

// ── AJAX Form Submit Helper ──
async function submitForm(url, data, successMsg) {
    try {
        const res = await fetch(url, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data)
        });
        const result = await res.json();
        if (result.success) {
            showToast(successMsg || result.message || 'Done!', 'success');
            return result;
        } else {
            showToast(result.message || 'Error occurred', 'danger');
            return null;
        }
    } catch (err) {
        showToast('Network error. Please try again.', 'danger');
        return null;
    }
}

// ── Delete Record ──
function deleteRecord(url, id, rowId, successMsg) {
    showConfirm('Delete Record', 'Are you sure? This cannot be undone.', async () => {
        const result = await submitForm(url, { id }, successMsg || 'Deleted successfully');
        if (result) {
            const row = document.getElementById(rowId);
            if (row) {
                row.style.opacity = '0';
                row.style.transition = 'opacity 0.3s';
                setTimeout(() => row.remove(), 300);
            }
        }
    });
}

// ── Status Update ──
async function updateStatus(url, id, status, badge) {
    const result = await submitForm(url, { id, status }, 'Status updated');
    if (result && badge) {
        badge.className = 'badge badge-' + getStatusClass(status);
        badge.textContent = status;
    }
}
function getStatusClass(s) {
    const m = { pending:'warning', processing:'info', confirmed:'primary', shipped:'purple', delivered:'success', cancelled:'danger', refunded:'secondary', paid:'success', unpaid:'danger' };
    return m[s] || 'secondary';
}

// ── Table Search Filter ──
function initTableSearch(inputId, tableId) {
    const input = document.getElementById(inputId);
    const tbody = document.querySelector(`#${tableId} tbody`);
    if (!input || !tbody) return;
    input.addEventListener('input', () => {
        const q = input.value.toLowerCase();
        tbody.querySelectorAll('tr').forEach(row => {
            row.style.display = row.textContent.toLowerCase().includes(q) ? '' : 'none';
        });
    });
}

// ── Format Currency ──
function formatINR(amount) {
    return '₹' + Number(amount).toLocaleString('en-IN', { maximumFractionDigits: 0 });
}

// ── Chart defaults ──
if (typeof Chart !== 'undefined') {
    Chart.defaults.color = '#9A9BA8';
    Chart.defaults.borderColor = 'rgba(255,255,255,0.06)';
    Chart.defaults.font.family = 'DM Sans';
}

// ── Revenue Chart ──
function initRevenueChart(chartData) {
    const ctx = document.getElementById('revenueChart');
    if (!ctx) return;
    new Chart(ctx, {
        type: 'line',
        data: {
            labels: chartData.map(d => d.month),
            datasets: [{
                label: 'Revenue (₹)',
                data: chartData.map(d => d.revenue),
                borderColor: '#C9A84C',
                backgroundColor: 'rgba(201,168,76,0.08)',
                borderWidth: 2.5,
                pointBackgroundColor: '#C9A84C',
                pointBorderColor: '#0A0B0E',
                pointBorderWidth: 2,
                pointRadius: 5,
                tension: 0.4,
                fill: true,
            }]
        },
        options: {
            responsive: true, maintainAspectRatio: false,
            plugins: { legend: { display: false }, tooltip: {
                backgroundColor: '#161820',
                borderColor: 'rgba(201,168,76,0.3)',
                borderWidth: 1,
                callbacks: { label: ctx => ' ₹' + Number(ctx.raw).toLocaleString('en-IN') }
            }},
            scales: {
                x: { grid: { color: 'rgba(255,255,255,0.04)' } },
                y: { grid: { color: 'rgba(255,255,255,0.04)' }, ticks: {
                    callback: v => '₹' + (v >= 1000 ? (v/1000).toFixed(0)+'K' : v)
                }}
            }
        }
    });
}

// ── Order Status Doughnut ──
function initOrderChart(data) {
    const ctx = document.getElementById('orderChart');
    if (!ctx) return;
    new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: Object.keys(data),
            datasets: [{
                data: Object.values(data),
                backgroundColor: ['#F39C12','#3498DB','#C9A84C','#9B59B6','#2ECC71','#E74C3C'],
                borderWidth: 0,
                hoverOffset: 8,
            }]
        },
        options: {
            responsive: true, maintainAspectRatio: false,
            cutout: '70%',
            plugins: {
                legend: { position: 'right', labels: { padding: 16, font: { size: 12 } } },
                tooltip: {
                    backgroundColor: '#161820',
                    borderColor: 'rgba(201,168,76,0.3)',
                    borderWidth: 1,
                }
            }
        }
    });
}

// ── Auto-hide flash messages ──
setTimeout(() => {
    document.querySelectorAll('.flash-msg').forEach(el => {
        el.style.opacity = '0';
        el.style.transition = 'opacity 0.5s';
        setTimeout(() => el.remove(), 500);
    });
}, 3000);

// ── Animate stat numbers ──
function animateCounters() {
    document.querySelectorAll('[data-count]').forEach(el => {
        const target = parseFloat(el.dataset.count);
        const isAmount = el.dataset.type === 'amount';
        const duration = 1200;
        const start = performance.now();
        const update = (time) => {
            const progress = Math.min((time - start) / duration, 1);
            const eased = 1 - Math.pow(1 - progress, 3);
            const current = target * eased;
            el.textContent = isAmount ? '₹' + Math.floor(current).toLocaleString('en-IN') : Math.floor(current).toLocaleString('en-IN');
            if (progress < 1) requestAnimationFrame(update);
        };
        requestAnimationFrame(update);
    });
}
document.addEventListener('DOMContentLoaded', animateCounters);
