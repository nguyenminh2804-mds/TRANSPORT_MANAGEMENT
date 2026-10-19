// ============================================================
// TRIPS API
// ============================================================

const API_BASE_URL = new URL(
    '../../backend/public/index.php',
    window.location.href
);


// ============================================================
// API REQUEST
// ============================================================

async function tripRequest(route, options = {}) {

    const url = new URL(API_BASE_URL.href);

    /*
     * route được truyền riêng vào query parameter.
     *
     * Ví dụ:
     *
     * /api/trips
     *
     * sẽ trở thành:
     *
     * index.php?route=%2Fapi%2Ftrips
     */

    url.searchParams.set(
        'route',
        route
    );


    let response;


    try {

        response = await fetch(
            url.href,
            {
                ...options,

                credentials: 'same-origin',

                cache: 'no-store'
            }
        );


    } catch (error) {

        console.error(
            'Lỗi kết nối:',
            error
        );

        throw new Error(
            'Lỗi kết nối mạng, vui lòng kiểm tra server.'
        );

    }


    /*
     * Phiên đăng nhập hết hạn
     */
    if (response.status === 401) {

        window.location.href =
            '../customer/login.html';

        throw new Error(
            'Phiên đăng nhập hết hạn.'
        );
    }


    /*
     * Đọc JSON từ server
     */
    let result;


    try {

        result = await response.json();


    } catch (error) {

        throw new Error(
            `Server trả dữ liệu không hợp lệ. HTTP ${response.status}`
        );

    }


    /*
     * API báo lỗi
     */
    if (
        !response.ok ||
        !result ||
        result.success !== true
    ) {

        throw new Error(
            result?.message ||
            `Lỗi truy xuất dữ liệu. HTTP ${response.status}`
        );

    }


    return result;
}


// ============================================================
// LẤY DANH SÁCH CHUYẾN
// ============================================================

async function loadTrips() {

    try {

        const res = await tripRequest(
            '/api/trips',
            {
                method: 'GET'
            }
        );


        return Array.isArray(res.data)
            ? res.data
            : [];


    } catch (error) {

        console.error(
            'Lỗi loadTrips:',
            error.message
        );

        return [];

    }
}


// ============================================================
// TẠO CHUYẾN
// ============================================================

async function createTrip(data) {

    try {

        return await tripRequest(
            '/api/trips',
            {
                method: 'POST',

                headers: {
                    'Content-Type':
                        'application/json'
                },

                body: JSON.stringify(data)
            }
        );


    } catch (error) {

        console.error(
            'Lỗi createTrip:',
            error.message
        );

        return {
            success: false,
            message: error.message
        };

    }
}


// ============================================================
// LẤY CHI TIẾT MỘT CHUYẾN
// ============================================================

async function getTrip(id) {

    try {

        if (
            id === null ||
            id === undefined ||
            id === ''
        ) {

            return {
                success: false,
                message: 'Thiếu ID chuyến xe.'
            };

        }


        /*
         * Backend đã có:
         *
         * GET /api/trips/show?id=ID
         *
         * Router mới sẽ tự loại bỏ query string
         * trước khi tìm route.
         */

        return await tripRequest(
            `/api/trips/show?id=${encodeURIComponent(id)}`,
            {
                method: 'GET'
            }
        );


    } catch (error) {

        console.error(
            'Lỗi getTrip:',
            error.message
        );

        return {
            success: false,
            message: error.message
        };

    }
}


// ============================================================
// CẬP NHẬT CHUYẾN
// ============================================================

async function updateTrip(id, data) {

    try {

        if (
            id === null ||
            id === undefined ||
            id === ''
        ) {

            return {
                success: false,
                message: 'Thiếu ID chuyến xe.'
            };

        }


        /*
         * Backend:
         *
         * PUT /api/trips?id=ID
         */

        return await tripRequest(
            `/api/trips?id=${encodeURIComponent(id)}`,
            {
                method: 'PUT',

                headers: {
                    'Content-Type':
                        'application/json'
                },

                body: JSON.stringify(data)
            }
        );


    } catch (error) {

        console.error(
            'Lỗi updateTrip:',
            error.message
        );

        return {
            success: false,
            message: error.message
        };

    }
}


// ============================================================
// CẬP NHẬT TRẠNG THÁI
// ============================================================

async function updateTripStatus(id, status) {

    try {

        if (
            id === null ||
            id === undefined ||
            id === ''
        ) {

            return {
                success: false,
                message: 'Thiếu ID chuyến xe.'
            };

        }


        return await tripRequest(
            `/api/trips/status?id=${encodeURIComponent(id)}`,
            {
                method: 'PUT',

                headers: {
                    'Content-Type':
                        'application/json'
                },

                body: JSON.stringify({
                    status: status
                })
            }
        );


    } catch (error) {

        console.error(
            'Lỗi updateTripStatus:',
            error.message
        );

        return {
            success: false,
            message: error.message
        };

    }
}


// ============================================================
// XÓA CHUYẾN
// ============================================================

async function deleteTrip(id) {

    try {

        if (
            id === null ||
            id === undefined ||
            id === ''
        ) {

            return {
                success: false,
                message: 'Thiếu ID chuyến xe.'
            };

        }


        return await tripRequest(
            `/api/trips?id=${encodeURIComponent(id)}`,
            {
                method: 'DELETE'
            }
        );


    } catch (error) {

        console.error(
            'Lỗi deleteTrip:',
            error.message
        );

        return {
            success: false,
            message: error.message
        };

    }
}