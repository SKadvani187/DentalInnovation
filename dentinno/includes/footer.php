    </main>
</div>

<!-- Toast Notifications -->
<div class="toast-container" id="toastContainer"></div>

<!-- Confirm Modal -->
<div class="modal-overlay" id="confirmModal" style="display:none;">
    <div class="modal-box">
        <div class="modal-icon"><i class="fa-solid fa-triangle-exclamation"></i></div>
        <h3 class="modal-title" id="confirmTitle">Are you sure?</h3>
        <p class="modal-msg" id="confirmMsg">This action cannot be undone.</p>
        <div class="modal-actions">
            <button class="btn btn-ghost" onclick="closeConfirm()">Cancel</button>
            <button class="btn btn-danger" id="confirmOk">Delete</button>
        </div>
    </div>
</div>

<script src="<?= APP_URL ?>/assets/js/app.js"></script>
</body>
</html>
