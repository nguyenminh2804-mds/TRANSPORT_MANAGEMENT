const API_BASE_URL =
    'http://localhost:19160/TRANSPORT_MANAGEMENT/backend/public/index.php';

const loginForm = document.getElementById('loginForm');
const loginMessage = document.getElementById('loginMessage');

loginForm.addEventListener('submit', async function (event) {
    event.preventDefault();

    const username = document.getElementById('username').value.trim();
    const password = document.getElementById('password').value;

    loginMessage.innerHTML = '';

    try {
        const response = await fetch(
            `${API_BASE_URL}?route=/api/login`,
            {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                credentials: 'include',
                body: JSON.stringify({
                    username: username,
                    password: password
                })
            }
        );

        const result = await response.json();

        if (!result.success) {
            loginMessage.innerHTML = `
                <div class="alert alert-danger">
                    ${result.message}
                </div>
            `;
            return;
        }

        sessionStorage.setItem(
            'user',
            JSON.stringify(result.data)
        );

        if (result.data.role === 'CUSTOMER') {
            window.location.href = 'dashboard.html';
        } else if (result.data.role === 'ADMIN') {
            window.location.href = '../admin/dashboard.html';
        } else {
            window.location.href = '../transport/dashboard.html';
        }

    } catch (error) {
        console.error('Login error:', error);

        loginMessage.innerHTML = `
            <div class="alert alert-danger">
                Không thể kết nối đến máy chủ.
            </div>
        `;
    }
});