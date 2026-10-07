const API_BASE_URL =
    'http://localhost:19160/TRANSPORT_MANAGEMENT/backend/public/index.php';

const loginForm = document.getElementById('loginForm');
const loginMessage = document.getElementById('loginMessage');

loginForm.addEventListener('submit', async function (event) {
    event.preventDefault();

    const username =
        document.getElementById('username').value.trim();

    const password =
        document.getElementById('password').value;

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

        /*
        |--------------------------------------------------------------------------
        | Đăng nhập thất bại
        |--------------------------------------------------------------------------
        */

        if (!result.success) {
            loginMessage.innerHTML = `
                <div class="alert alert-danger">
                    ${result.message}
                </div>
            `;

            return;
        }

        /*
        |--------------------------------------------------------------------------
        | Lưu thông tin user
        |--------------------------------------------------------------------------
        */

        sessionStorage.setItem(
            'user',
            JSON.stringify(result.data)
        );

        /*
        |--------------------------------------------------------------------------
        | Phân quyền và chuyển trang
        |--------------------------------------------------------------------------
        */

        const role = result.data.role;

        switch (role) {

            /*
            |--------------------------------------------------------------------------
            | ADMIN
            |--------------------------------------------------------------------------
            */

            case 'ADMIN':

                window.location.href =
                    '../admin/dashboard.html';

                break;


            /*
            |--------------------------------------------------------------------------
            | STAFF
            |--------------------------------------------------------------------------
            */

            case 'STAFF':

                window.location.href =
                    '../transport/dashboard.html';

                break;


            /*
            |--------------------------------------------------------------------------
            | DRIVER
            |--------------------------------------------------------------------------
            */

            case 'DRIVER':

                window.location.href =
                    '../driver/dashboard.html';

                break;


            /*
            |--------------------------------------------------------------------------
            | CUSTOMER
            |--------------------------------------------------------------------------
            */

            case 'CUSTOMER':

                window.location.href =
                    'dashboard.html';

                break;


            /*
            |--------------------------------------------------------------------------
            | ROLE KHÔNG HỢP LỆ
            |--------------------------------------------------------------------------
            */

            default:

                loginMessage.innerHTML = `
                    <div class="alert alert-danger">
                        Vai trò tài khoản không hợp lệ.
                    </div>
                `;

                sessionStorage.removeItem('user');

                break;
        }

    } catch (error) {

        console.error(
            'Login error:',
            error
        );

        loginMessage.innerHTML = `
            <div class="alert alert-danger">
                Không thể kết nối đến máy chủ.
            </div>
        `;
    }
});