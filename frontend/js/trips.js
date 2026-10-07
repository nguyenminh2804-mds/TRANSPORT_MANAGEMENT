const BASE_URL = 'http://localhost/TRANSPORT_MANAGEMENT/backend/public/index.php?url=trips';

document.addEventListener('DOMContentLoaded', loadTrips);

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
                        <td>${trip.vehicle_plate || 'Chưa có'}</td>
                        <td>${trip.goods_name || 'Không rõ'}</td>
                        <td>${trip.start_location} ➔ ${trip.end_location}</td>
                        <td><span style="padding: 4px 8px; background: #e0f7fa; color: #006064; border-radius: 4px;">${trip.status}</span></td>
                        <td><button onclick="trackTrip(${trip.id})" style="background: #05cd99;">Chi tiết</button></td>
                    </tr>`;
                });
                document.getElementById('tripTableBody').innerHTML = html;
            }
        });
}

document.getElementById('createTripForm').addEventListener('submit', function(e) {
    e.preventDefault();
    const tripData = {
        driver_name: document.getElementById('driverName').value,
        driver_phone: document.getElementById('driverPhone').value,
        vehicle_plate: document.getElementById('vehiclePlate').value,
        start_location: document.getElementById('startLocation').value,
        end_location: document.getElementById('endLocation').value,
        goods_name: document.getElementById('goodsName').value,
        goods_value: document.getElementById('goodsValue').value,
        weight: document.getElementById('weight').value,
        receiver_name: document.getElementById('receiverName').value,
        receiver_phone: document.getElementById('receiverPhone').value
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
            loadTrips(); 
        }
        setTimeout(() => document.getElementById('msg').innerText = '', 3000);
    });
});

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
                document.getElementById('trackPhone').innerText = data.driver_phone;
                document.getElementById('trackVehicle').innerText = data.vehicle_plate;
                document.getElementById('trackRoute').innerText = `${data.start_location} ➔ ${data.end_location}`;
                
                document.getElementById('trackGoods').innerText = data.goods_name;
                document.getElementById('trackWeight').innerText = data.weight;
                document.getElementById('trackValue').innerText = new Intl.NumberFormat('vi-VN').format(data.goods_value);
                document.getElementById('trackReceiver').innerText = data.receiver_name;
                document.getElementById('trackReceiverPhone').innerText = data.receiver_phone;
            }
        });
}