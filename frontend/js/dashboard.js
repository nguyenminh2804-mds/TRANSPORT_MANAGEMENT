// Customer pages are located in frontend/customer/; resolve relative to the page.
async function customerRequest(route, options = {}) {
    const url = new URL('../../backend/public/index.php', window.location.href);
    url.searchParams.set('route', route);
    let response;
    try {
        response = await fetch(url.href, { ...options, credentials: 'same-origin', cache: 'no-store' });
    } catch {
        customerApiError('Không thể kết nối tới máy chủ. Hãy kiểm tra Apache/XAMPP rồi thử lại.');
        throw new Error('Customer API connection failed');
    }
    if (response.status === 401) {
        window.location.href = 'login.html';
        throw new Error('Session expired');
    }
    let result;
    try { result = await response.json(); }
    catch {
        customerApiError(`Máy chủ trả HTTP ${response.status} nhưng JSON không hợp lệ.`);
        throw new Error('Invalid customer API JSON');
    }
    if (!response.ok || !result || typeof result.success !== 'boolean' || !result.success) {
        customerApiError(`Yêu cầu thất bại (HTTP ${response.status}). ${response.status < 500 && typeof result?.message === 'string' ? result.message : 'Vui lòng thử lại sau.'}`);
        throw new Error('Customer API request failed');
    }
    return { ...response, json: async () => result };
}

function customerApiError(message) {
    let banner = document.getElementById('customerApiError');
    if (!banner) {
        banner = document.createElement('div');
        banner.id = 'customerApiError';
        banner.className = 'alert alert-danger m-3';
        banner.setAttribute('role', 'alert');
        document.body.prepend(banner);
    }
    banner.textContent = message;
}



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

        const response = await customerRequest('/api/me',
            {
                method: 'GET',
                credentials: 'same-origin'
            }
        );


        const result = await response.json();


        /*
        |--------------------------------------------------------------------------
        | Not logged in
        |--------------------------------------------------------------------------
        */

        if (!result.success) {
            customerApiError('Không thể tải phiên đăng nhập.');

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

                const response = await customerRequest('/api/logout',
                    {
                        method: 'POST',
                        credentials: 'same-origin'
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