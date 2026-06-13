<!-- Logout Confirmation Modal -->
<div id="logoutModal" class="modal-overlay">
    <div class="modal-content">
        <i class="fas fa-sign-out-alt" style="font-size: 50px; color: #dc3545; margin-bottom: 15px;"></i>
        <h4>Confirm Logout</h4>
        <p>Are you sure you want to logout from your account?</p>
        <div class="modal-buttons">
            <button id="confirmLogout" class="modal-btn-confirm">Yes, Logout</button>
            <button id="cancelLogout" class="modal-btn-cancel">Cancel</button>
        </div>
    </div>
</div>

<style>
.modal-overlay {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.5);
    justify-content: center;
    align-items: center;
    z-index: 2000;
    backdrop-filter: blur(4px);
}
.modal-content {
    background: white;
    border-radius: 20px;
    padding: 30px;
    width: 380px;
    text-align: center;
    animation: modalFadeIn 0.3s ease;
}
@keyframes modalFadeIn {
    from { opacity: 0; transform: scale(0.95); }
    to { opacity: 1; transform: scale(1); }
}
.modal-content i { font-size: 50px; margin-bottom: 15px; }
.modal-content h4 { font-size: 20px; font-weight: 600; margin-bottom: 10px; }
.modal-content p { color: #666; margin-bottom: 20px; }
.modal-buttons { display: flex; gap: 15px; justify-content: center; }
.modal-btn-confirm {
    background: #dc3545;
    color: white;
    border: none;
    padding: 10px 25px;
    border-radius: 30px;
    cursor: pointer;
    font-weight: 500;
}
.modal-btn-cancel {
    background: #6c757d;
    color: white;
    border: none;
    padding: 10px 25px;
    border-radius: 30px;
    cursor: pointer;
    font-weight: 500;
}
.modal-btn-confirm:hover { background: #c82333; transform: scale(1.02); }
.modal-btn-cancel:hover { background: #5a6268; transform: scale(1.02); }
</style>

<script>
function showLogoutConfirm() {
    document.getElementById('logoutModal').style.display = 'flex';
}
function closeLogoutModal() {
    document.getElementById('logoutModal').style.display = 'none';
}
document.getElementById('confirmLogout').onclick = function() {
    window.location.href = '../../logout.php';
};
document.getElementById('cancelLogout').onclick = function() {
    closeLogoutModal();
};
window.onclick = function(event) {
    if (event.target == document.getElementById('logoutModal')) {
        closeLogoutModal();
    }
};
</script>