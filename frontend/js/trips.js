// Quản lý các gọi API cho module Trips (Chuyến vận chuyển)
const API_BASE_URL = new URL('../../backend/public/index.php', window.location.href);

// Hàm gọi API dùng chung cho Trips
async function tripRequest(route, options = {}) {
    const url = new URL(API_BASE_URL);
    url.searchParams.set('route', route);

    let response;
    try {
        response = await fetch(url.href, {
            ...options,
            credentials: 'same-origin',
            cache: 'no-store'
        });
    } catch (error) {
        console.error('Network error:', error);
        throw new Error('Không thể kết nối tới máy chủ.');
    }

    if (response.status === 401) {
        window.location.href = '../customer/login.html'; // Hoặc trang login chung
        throw new Error('Phiên đăng nhập đã hết hạn.');
    }

    let result;
    try {
        result = await response.json();
    } catch {
        throw new Error(`Phản hồi từ máy chủ không hợp lệ (HTTP ${response.status}).`);
    }

    if (!response.ok || !result?.success) {
        throw new Error(result?.message || `Yêu cầu thất bại (HTTP ${response.status}).`);
    }

    return result;
}

// 1. Lấy danh sách chuyến đi (Tự động lọc theo Role ở Backend)
async function loadTrips() {
    try {
        const res = await tripRequest('/api/trips', { method: 'GET' });
        return res.data || [];
    } catch (error) {
        console.error('Lỗi tải danh sách chuyến:', error.message);
        return [];
    }
}

// 2. Tạo mới chuyến đi (Dành cho Admin / Staff)
async function createTrip(tripData) {
    try {
        const res = await tripRequest('/api/trips', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(tripData)
        });
        return { success: true, message: res.message };
    } catch (error) {
        return { success: false, message: error.message };
    }
}

// 3. Cập nhật thông tin chuyến đi (Admin / Staff)
async function updateTrip(tripId, tripData) {
    try {
        const res = await tripRequest(`/api/trips?id=${tripId}`, {
            method: 'PUT',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(tripData)
        });
        return { success: true, message: res.message };
    } catch (error) {
        return { success: false, message: error.message };
    }
}

// 4. Cập nhật trạng thái chuyến (Staff hoặc Driver)
// Các trạng thái: PLANNED, IN_PROGRESS, COMPLETED, CANCELLED
async function updateTripStatus(tripId, newStatus) {
    try {
        const res = await tripRequest(`/api/trips/status?id=${tripId}`, {
            method: 'PUT',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ status: newStatus })
        });
        return { success: true, message: res.message };
    } catch (error) {
        return { success: false, message: error.message };
    }
}

// 5. Xóa chuyến đi (Admin / Staff)
async function deleteTrip(tripId) {
    try {
        const res = await tripRequest(`/api/trips?id=${tripId}`, {
            method: 'DELETE'
        });
        return { success: true, message: res.message };
    } catch (error) {
        return { success: false, message: error.message };
    }
}