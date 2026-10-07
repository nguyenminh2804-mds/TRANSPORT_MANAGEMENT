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


const welcomeUser = document.getElementById('welcomeUser');

const logoutBtn = document.getElementById('logoutBtn');

const customerName = document.getElementById('customerName');

const customerPhone = document.getElementById('customerPhone');

const customerEmail = document.getElementById('customerEmail');

const customerAddress = document.getElementById('customerAddress');

const ordersTableBody =
    document.getElementById('ordersTableBody');

const trackOrderForm =
    document.getElementById('trackOrderForm');

const orderCode =
    document.getElementById('orderCode');

const orderResult =
    document.getElementById('orderResult');

const reloadOrdersBtn =
    document.getElementById('reloadOrdersBtn');


// ================================
// Load user information
// ================================

async function loadUser() {
    try {
        const response = await customerRequest('/api/me',
            {
                method: 'GET',
                credentials: 'same-origin'
            }
        );

        const result = await response.json();

        if (!result.success) {
            customerApiError('Không thể tải phiên đăng nhập.');
            return;
        }

        welcomeUser.textContent =
            `Xin chào, ${result.data.full_name}`;

    } catch (error) {
        console.error('Load user error:', error);
    }
}


// ================================
// Load customer profile
// ================================

async function loadCustomerProfile() {
    try {
        const response = await customerRequest('/api/customer/profile',
            {
                method: 'GET',
                credentials: 'same-origin'
            }
        );

        const result = await response.json();

        if (!result.success) {
            return;
        }

        const customer = result.data;

        customerName.textContent =
            customer.name || '-';

        customerPhone.textContent =
            customer.phone || '-';

        customerEmail.textContent =
            customer.email || '-';

        customerAddress.textContent =
            customer.address || '-';

    } catch (error) {
        console.error(
            'Load customer profile error:',
            error
        );
    }
}


// ================================
// Load customer orders
// ================================

async function loadOrders() {
    ordersTableBody.innerHTML = `
        <tr>
            <td colspan="8" class="text-center">
                Đang tải dữ liệu...
            </td>
        </tr>
    `;

    try {
        const response = await customerRequest('/api/customer/orders',
            {
                method: 'GET',
                credentials: 'same-origin'
            }
        );

        const result = await response.json();

        if (!result.success) {
            ordersTableBody.innerHTML = `
                <tr>
                    <td colspan="8" class="text-center text-danger">
                        ${result.message}
                    </td>
                </tr>
            `;

            return;
        }

        const orders = result.data;

        if (orders.length === 0) {
            ordersTableBody.innerHTML = `
                <tr>
                    <td colspan="8" class="text-center">
                        Chưa có đơn hàng
                    </td>
                </tr>
            `;

            return;
        }

        ordersTableBody.innerHTML = '';

        orders.forEach(order => {

            const row = document.createElement('tr');

            row.innerHTML = `
                <td>
                    <strong>
                        ${order.order_code}
                    </strong>
                </td>

                <td>
                    ${order.pickup_address}
                </td>

                <td>
                    ${order.delivery_address}
                </td>

                <td>
                    ${order.total_weight}
                </td>

                <td>
                    ${formatCurrency(order.shipping_fee)}
                </td>

                <td>
                    ${order.payment_status}
                </td>

                <td>
                    <span class="badge bg-primary">
                        ${order.status}
                    </span>
                </td>

                <td>
                    ${formatDate(order.created_at)}
                </td>
            `;

            ordersTableBody.appendChild(row);
        });

    } catch (error) {
        console.error(
            'Load orders error:',
            error
        );

        ordersTableBody.innerHTML = `
            <tr>
                <td colspan="8" class="text-center text-danger">
                    Không thể tải danh sách đơn hàng
                </td>
            </tr>
        `;
    }
}


// ================================
// Track order
// ================================

trackOrderForm.addEventListener(
    'submit',
    async function (event) {

        event.preventDefault();

        const code = orderCode.value.trim();

        if (!code) {
            return;
        }

        orderResult.innerHTML = `
            <div class="text-center">
                Đang tra cứu...
            </div>
        `;

        try {

            const response = await customerRequest('/api/customer/orders/track',
                {
                    method: 'POST',

                    headers: {
                        'Content-Type': 'application/json'
                    },

                    credentials: 'same-origin',

                    body: JSON.stringify({
                        order_code: code
                    })
                }
            );

            const result = await response.json();

            if (!result.success) {

                orderResult.innerHTML = `
                    <div class="alert alert-danger">
                        ${result.message}
                    </div>
                `;

                return;
            }

            const order = result.data;

            orderResult.innerHTML = `
                <div class="card border-primary">

                    <div class="card-body">

                        <h6 class="card-title">
                            Kết quả tra cứu
                        </h6>

                        <p>
                            <strong>Mã đơn:</strong>
                            ${order.order_code}
                        </p>

                        <p>
                            <strong>Nơi lấy:</strong>
                            ${order.pickup_address}
                        </p>

                        <p>
                            <strong>Nơi giao:</strong>
                            ${order.delivery_address}
                        </p>

                        <p>
                            <strong>Khối lượng:</strong>
                            ${order.total_weight}
                        </p>

                        <p>
                            <strong>Phí vận chuyển:</strong>
                            ${formatCurrency(order.shipping_fee)}
                        </p>

                        <p>
                            <strong>Thanh toán:</strong>
                            ${order.payment_status}
                        </p>

                        <p class="mb-0">
                            <strong>Trạng thái:</strong>

                            <span class="badge bg-primary">
                                ${order.status}
                            </span>
                        </p>

                    </div>

                </div>
            `;

        } catch (error) {

            console.error(
                'Track order error:',
                error
            );

            orderResult.innerHTML = `
                <div class="alert alert-danger">
                    Không thể kết nối đến máy chủ.
                </div>
            `;
        }
    }
);


// ================================
// Logout
// ================================

logoutBtn.addEventListener(
    'click',
    async function () {

        try {

            await customerRequest('/api/logout',
                {
                    method: 'POST',
                    credentials: 'same-origin'
                }
            );

            sessionStorage.removeItem('user');
            window.location.href = 'login.html';

        } catch (error) {

            console.error(
                'Logout error:',
                error
            );

        }

    }
);


// ================================
// Helper functions
// ================================

function formatCurrency(value) {

    const number = Number(value);

    if (Number.isNaN(number)) {
        return '-';
    }

    return new Intl.NumberFormat(
        'vi-VN'
    ).format(number) + ' VNĐ';
}


function formatDate(value) {

    if (!value) {
        return '-';
    }

    const date = new Date(value);

    if (Number.isNaN(date.getTime())) {
        return value;
    }

    return date.toLocaleDateString('vi-VN');
}


// ================================
// Initial load
// ================================

loadUser();

loadCustomerProfile();

loadOrders();


// ================================
// Reload orders
// ================================

reloadOrdersBtn.addEventListener(
    'click',
    loadOrders
);