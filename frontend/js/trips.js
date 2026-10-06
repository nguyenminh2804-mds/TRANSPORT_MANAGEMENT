const BASE_URL = 'http://localhost/TRANSPORT_MANAGEMENT/backend/public/index.php?url=trips';

document.addEventListener('DOMContentLoaded', loadTrips);

// 1. Lấy danh sách chuyến xe
function loadTrips() {
    fetch(BASE_URL)
        .then(res => res.json())
        .then(res => {
            if (res.status === 'success') {
                let html = '';
                res.data.forEach(trip => {
                    html += `<tr>
                        <td><strong>TRIP-${trip.id}</strong></td>
                        <td>${trip.driver_name || 'Chưa có'}</td>
                        <td>${trip.license_plate || 'Chưa có'}</td>
                        <td>${trip.start_location} ➔ ${trip.end_location}</td>
                        <td>${trip.status}</td>
                        <td><button onclick="trackTrip(${trip.id})" style="background: #05cd99;">Theo dõi</button></td>
                    </tr>`;
                });
                document.getElementById('tripTableBody').innerHTML = html;
            }
        });
}

// 2. Tạo chuyến mới
document.getElementById('createTripForm').addEventListener('submit', function(e) {
    e.preventDefault();
    const tripData = {
        driver_id: document.getElementById('driverId').value,
        vehicle_id: document.getElementById('vehicleId').value,
        start_location: document.getElementById('startLocation').value,
        end_location: document.getElementById('endLocation').value
    };

    fetch(BASE_URL + '&action=create', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(tripData)
    })
    .then(res => res.json())
    .then(data => {
        document.getElementById('msg').innerText = data.message;
        if(data.status === 'success') {
            document.getElementById('createTripForm').reset();
            loadTrips(); // Tải lại bảng ngay lập tức
        }
        setTimeout(() => document.getElementById('msg').innerText = '', 3000);
    });
});

// 3. Theo dõi chuyến
function trackTrip(id) {
    fetch(BASE_URL + `&action=track&id=${id}`)
        .then(res => res.json())
        .then(res => {
            if (res.status === 'success') {
                const data = res.data;
                document.getElementById('trackingCard').style.display = 'block';
                document.getElementById('trackId').innerText = data.id;
                document.getElementById('trackStatus').innerText = data.status;
                document.getElementById('trackDriver').innerText = data.driver_name;
                document.getElementById('trackPhone').innerText = data.phone;
                document.getElementById('trackVehicle').innerText = data.license_plate;
                document.getElementById('trackRoute').innerText = `${data.start_location} ➔ ${data.end_location}`;
            }
        });
}