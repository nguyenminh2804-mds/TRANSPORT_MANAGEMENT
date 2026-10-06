document.addEventListener('DOMContentLoaded', () => {
    const API_URL = 'http://localhost/TRANSPORT_MANAGEMENT/backend/public/index.php?url=statistics';

    fetch(API_URL)
        .then(response => response.json())
        .then(res => {
            if (res.status === 'success') {
                const data = res.data;
                document.getElementById('statOrders').innerText = data.total_orders;
                // Định dạng tiền tệ
                document.getElementById('statRevenue').innerText = new Intl.NumberFormat('vi-VN').format(data.revenue) + ' đ';
                document.getElementById('statTrips').innerText = data.active_trips;
            }
        })
        .catch(err => console.error("Lỗi lấy dữ liệu thống kê:", err));
});