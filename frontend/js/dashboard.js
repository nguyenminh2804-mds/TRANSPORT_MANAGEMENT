const API_BASE_URL =
    'http://localhost:19160/TRANSPORT_MANAGEMENT/backend/public/index.php';


/*
|--------------------------------------------------------------------------
| Page loaded
|--------------------------------------------------------------------------
*/

document.addEventListener('DOMContentLoaded', () => {

    loadCurrentUser();

    setupLogout();

});


/*
|--------------------------------------------------------------------------
| Load current user
|--------------------------------------------------------------------------
*/

async function loadCurrentUser() {

    try {

        const response = await fetch(
            `${API_BASE_URL}?route=/api/me`,
            {
                method: 'GET',
                credentials: 'include'
            }
        );


        const result = await response.json();


        /*
        |--------------------------------------------------------------------------
        | Not logged in
        |--------------------------------------------------------------------------
        */

        if (!result.success) {

            window.location.href = 'login.html';

            return;
        }


        /*
        |--------------------------------------------------------------------------
        | Display username
        |--------------------------------------------------------------------------
        */

        const welcomeUser =
            document.getElementById('welcomeUser');


        if (welcomeUser) {

            welcomeUser.textContent =
                `Xin chào, ${result.data.full_name}`;

        }

    } catch (error) {

        console.error(
            'Load current user error:',
            error
        );

        window.location.href =
            'login.html';

    }

}


/*
|--------------------------------------------------------------------------
| Logout
|--------------------------------------------------------------------------
*/

function setupLogout() {

    const logoutBtn =
        document.getElementById('logoutBtn');


    if (!logoutBtn) {

        return;
    }


    logoutBtn.addEventListener(
        'click',
        async () => {

            try {

                const response = await fetch(
                    `${API_BASE_URL}?route=/api/logout`,
                    {
                        method: 'POST',
                        credentials: 'include'
                    }
                );


                const result =
                    await response.json();


                if (result.success) {

                    window.location.href =
                        'login.html';

                    return;
                }


                alert(
                    result.message ||
                    'Đăng xuất thất bại'
                );

            } catch (error) {

                console.error(
                    'Logout error:',
                    error
                );

                alert(
                    'Không thể kết nối đến máy chủ'
                );

            }

        }
    );

}