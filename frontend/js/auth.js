const API_BASE_URL = new URL(
    '../../backend/public/index.php',
    window.location.href
);
API_BASE_URL.searchParams.set('route', '/api/login');

const loginForm = document.getElementById('loginForm');
const loginMessage = document.getElementById('loginMessage');
const submitButton = loginForm?.querySelector?.('button[type="submit"]') ?? null;
let loginPending = false;

function showLoginMessage(text) {
    if (!loginMessage) return;

    const alert = document.createElement('div');
    alert.className = 'alert alert-danger';
    alert.textContent = text;
    loginMessage.replaceChildren(alert);
}

if (loginForm && loginMessage) {
    loginForm.addEventListener('submit', async function (event) {
        event.preventDefault();
        if (loginPending) return;

        const username = document.getElementById('username')?.value.trim() ?? '';
        const password = document.getElementById('password')?.value ?? '';
        loginMessage.replaceChildren();

        if (!username || !password) {
            showLoginMessage('Vui lòng nhập đầy đủ tài khoản và mật khẩu.');
            return;
        }

        loginPending = true;
        if (submitButton) submitButton.disabled = true;

        try {
            let response;

            try {
                response = await fetch(API_BASE_URL.href, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        Accept: 'application/json'
                    },
                    credentials: 'same-origin',
                    cache: 'no-store',
                    body: JSON.stringify({ username, password })
                });
            } catch (error) {
                console.error('Login connection error:', error);
                showLoginMessage(
                    'Không thể kết nối tới máy chủ. Hãy kiểm tra Apache/XAMPP rồi thử lại.'
                );
                return;
            }

            let result;

            try {
                result = await response.json();
            } catch {
                showLoginMessage(
                    `Máy chủ trả HTTP ${response.status} nhưng phản hồi không hợp lệ.`
                );
                return;
            }

            if (!response.ok || !result?.success) {
                showLoginMessage(
                    result?.message || `Đăng nhập thất bại (HTTP ${response.status}).`
                );
                return;
            }

            const role = result.data?.role;
            const destinations = {
                ADMIN: '../admin/dashboard.html',
                STAFF: '../transport/dashboard.html',
                DRIVER: '../driver/dashboard.html',
                CUSTOMER: 'dashboard.html'
            };

            if (!role || !destinations[role]) {
                showLoginMessage('Vai trò tài khoản không hợp lệ.');
                return;
            }

            try {
                sessionStorage.setItem('user', JSON.stringify(result.data));
            } catch (error) {
                console.warn('Không lưu được thông tin giao diện:', error);
            }

            window.location.href = destinations[role];
        } catch (error) {
            console.error('Login error:', error);
            showLoginMessage('Đã xảy ra lỗi khi đăng nhập. Vui lòng thử lại.');
        } finally {
            loginPending = false;
            if (submitButton) submitButton.disabled = false;
        }
    });
}