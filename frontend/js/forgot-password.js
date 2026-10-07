const API_BASE_URL = new URL('../../backend/public/index.php', window.location.href);
API_BASE_URL.searchParams.set('route', '/api/forgot-password');
const form = document.getElementById('forgotPasswordForm');
const message = document.getElementById('forgotMessage');
let pending = false;
function showMessage(text, success = false) {
    const alert = document.createElement('div');
    alert.className = 'alert ' + (success ? 'alert-success' : 'alert-danger');
    alert.textContent = text;
    message.replaceChildren(alert);
}
form.addEventListener('submit', async function (event) {
    event.preventDefault();
    if (pending) return;
    message.replaceChildren();
    const data = {
            username: document.getElementById('username').value.trim(),
            full_name: document.getElementById('fullName').value.trim(),
            new_password: document.getElementById('newPassword').value,
            confirm_password: document.getElementById('confirmPassword').value
    };
    if (data.new_password !== data.confirm_password) {
        showMessage('Mật khẩu xác nhận không khớp.');
        return;
    }
    pending = true;
    try {
        let response;
        try {
            response = await fetch(API_BASE_URL.href, {
                method: 'POST', credentials: 'same-origin', cache: 'no-store',
                headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
                body: JSON.stringify(data)
            });
        } catch {
            showMessage('Không thể kết nối tới máy chủ. Kiểm tra Apache/XAMPP và thử lại.');
            return;
        }
        let result;
        try { result = await response.json(); }
        catch {
            showMessage(`Máy chủ trả HTTP ${response.status} nhưng phản hồi không hợp lệ.`);
            return;
        }
        if (!response.ok || !result?.success) {
            showMessage(`Đặt lại mật khẩu thất bại (HTTP ${response.status}): ${result?.message || 'Vui lòng thử lại.'}`);
            return;
        }
        showMessage(result.message || 'Đặt lại mật khẩu thành công.', true);
        setTimeout(() => { window.location.href = 'login.html'; }, 1200);
    } finally { pending = false; }
});
