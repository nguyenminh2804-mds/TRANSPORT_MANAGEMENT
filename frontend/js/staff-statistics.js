(() => {
'use strict';
const endpoint=new URL('../../backend/public/index.php',location.href);let myChart=null,revision=0;
const ids=['statPendingOrders','statDeliveringOrders','statActiveTrips','statTotalRevenue','driverText','vehicleText','paymentText'];
function clear(invalidate=true){if(invalidate)revision++;for(const id of ids)document.getElementById(id).textContent='—';for(const id of ['driverProgress','vehicleProgress','paymentProgress'])document.getElementById(id).style.width='0%';if(myChart){myChart.destroy();myChart=null;}}
function message(value,error=false){const node=document.getElementById('statisticsMessage');node.textContent=value;node.className=error?'drivers-message error':'drivers-note';}
async function api(route){const url=new URL(endpoint);url.searchParams.set('route',route);const response=await fetch(url,{credentials:'same-origin',cache:'no-store',headers:{Accept:'application/json'}});if(response.status===401){location.replace('../customer/login.html');throw new Error('Vui lòng đăng nhập.');}let result;try{result=await response.json();}catch{throw new Error('Phản hồi thống kê không hợp lệ.');}if(!response.ok||!result?.success)throw new Error(response.status>=500?'Không tải được số liệu từ máy chủ.':result.message||'Không có quyền xem thống kê.');if(!Array.isArray(result.data))throw new Error('Dữ liệu thống kê không đúng định dạng.');return result.data;}
async function loadAllStatistics(){if(!window.staffSessionReady)return;clear();const current=revision;message('Đang tải thống kê…');document.getElementById('statisticsRefresh').disabled=true;try{const [orders,trips,drivers,vehicles]=await Promise.all([api('/api/orders'),api('/api/trips'),api('/api/drivers'),api('/api/vehicles')]);if(current!==revision||!window.staffSessionReady)return;processOrderStats(orders);processTripStats(trips);processResourceStats(drivers,vehicles);message(typeof Chart==='undefined'?'Đã tải số liệu; thư viện biểu đồ chưa tải được.':'Số liệu từ API; bấm Cập nhật số liệu để làm mới.');}catch(error){if(current===revision){clear(false);message(error.message,true);}}finally{if(current===revision)document.getElementById('statisticsRefresh').disabled=false;}}
        function processOrderStats(orders) {
            let pendingCount = 0;
            let confirmedCount = 0;
            let pickingUpCount = 0;
            let deliveringCount = 0;
            let completedCount = 0;
            let cancelledCount = 0;
            let totalRevenue = 0;
            let paidCount = 0;
            const totalOrders = orders.length;

            orders.forEach(o => {
                // Dùng cùng trạng thái hiệu lực với danh sách đơn hàng.
                const orderStatus = o.display_status || o.status;
                if (orderStatus === 'PENDING') pendingCount++;
                else if (orderStatus === 'CONFIRMED' || orderStatus === 'ASSIGNED') confirmedCount++;
                else if (orderStatus === 'PICKING_UP') pickingUpCount++;
                else if (orderStatus === 'IN_TRANSIT' || orderStatus === 'DELIVERING') deliveringCount++;
                else if (orderStatus === 'DELIVERED') completedCount++;
                else if (orderStatus === 'CANCELLED' || orderStatus === 'CANCELED') cancelledCount++;

                // Tính doanh thu (Tính tổng các đơn không bị hủy)
                if (orderStatus !== 'CANCELLED' && orderStatus !== 'CANCELED') {
                    totalRevenue += Number(o.shipping_fee || 0);
                }

                // Mẫu số gồm mọi đơn; tử số chỉ gồm đơn đã thanh toán chưa bị hủy.
                if (orderStatus !== 'CANCELLED' && orderStatus !== 'CANCELED' &&
                    (o.display_payment_status || o.payment_status) === 'PAID') paidCount++;
            });

            // In ra giao diện
            document.getElementById('statPendingOrders').textContent = pendingCount;
            document.getElementById('statDeliveringOrders').textContent = deliveringCount;
            document.getElementById('statTotalRevenue').textContent = totalRevenue.toLocaleString('vi-VN') + ' đ';

            // Cập nhật thanh tiến trình thanh toán
            const paymentPercent = totalOrders === 0 ? 0 : Math.round((paidCount / totalOrders) * 100);
            document.getElementById('paymentText').textContent = `${paymentPercent}% (${paidCount}/${totalOrders})`;
            document.getElementById('paymentProgress').style.width = `${paymentPercent}%`;

            // Vẽ biểu đồ
            renderChart(pendingCount, confirmedCount, pickingUpCount, deliveringCount, completedCount, cancelledCount);
        }

        // Xử lý thống kê CHUYẾN XE
        function processTripStats(trips) {
            // Đếm các chuyến đang có trạng thái IN_PROGRESS
            const activeTrips = trips.filter(t => t.status === 'IN_PROGRESS').length;
            document.getElementById('statActiveTrips').textContent = activeTrips;
        }

        // Xử lý thống kê TÀI XẾ & XE
        function processResourceStats(drivers, vehicles) {
            // Tài xế rảnh
            const totalDrivers = drivers.length;
            const availableDrivers = drivers.filter(d => (d.display_status || d.status) === 'available').length;
            const driverPercent = totalDrivers === 0 ? 0 : (availableDrivers / totalDrivers) * 100;

            document.getElementById('driverText').textContent = `${availableDrivers}/${totalDrivers}`;
            document.getElementById('driverProgress').style.width = `${driverPercent}%`;
            document.getElementById('driverProgress').className = `progress-bar ${driverPercent < 30 ? 'bg-danger' : 'bg-success'}`;

            // Xe rảnh
            const totalVehicles = vehicles.length;
            const availableVehicles = vehicles.filter(v => (v.display_status || v.status) === 'available').length;
            const vehiclePercent = totalVehicles === 0 ? 0 : (availableVehicles / totalVehicles) * 100;

            document.getElementById('vehicleText').textContent = `${availableVehicles}/${totalVehicles}`;
            document.getElementById('vehicleProgress').style.width = `${vehiclePercent}%`;
            document.getElementById('vehicleProgress').className = `progress-bar ${vehiclePercent < 30 ? 'bg-danger' : 'bg-info'}`;
        }

        // VẼ BIỂU ĐỒ TRÒN (Doughnut Chart)
        // VẼ BIỂU ĐỒ TRÒN (Doughnut Chart) - CÓ SỐ LƯỢNG VÀ PHẦN TRĂM
        // VẼ BIỂU ĐỒ TRÒN (Doughnut Chart)
        function renderChart(pending, confirmed, pickingUp, delivering, completed, cancelled) {
            if(typeof Chart==='undefined')return;
            const ctx = document.getElementById('orderStatusChart').getContext('2d');

            // Hủy biểu đồ cũ một cách sạch sẽ (Không cần xóa HTML)
            if (myChart) {
                myChart.destroy();
            }

            // Tính toán phần trăm
            const total = pending + confirmed + pickingUp + delivering + completed + cancelled;
            const getPercent = (val) => total === 0 ? 0 : Math.round((val / total) * 100);

            const chartLabels = [
                `Chờ xử lý: ${pending} đơn (${getPercent(pending)}%)`,
                `Đã xác nhận: ${confirmed} đơn (${getPercent(confirmed)}%)`,
                `Đang lấy hàng: ${pickingUp} đơn (${getPercent(pickingUp)}%)`,
                `Đang vận chuyển: ${delivering} đơn (${getPercent(delivering)}%)`,
                `Đã giao xong: ${completed} đơn (${getPercent(completed)}%)`,
                `Khách hủy: ${cancelled} đơn (${getPercent(cancelled)}%)`
            ];

            myChart = new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: chartLabels,
                    datasets: [{
                        data: [pending, confirmed, pickingUp, delivering, completed, cancelled],
                        backgroundColor: ['#ffc107', '#0dcaf0', '#6f42c1', '#0d6efd', '#198754', '#dc3545'],
                        borderWidth: 0,
                        hoverOffset: 5
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false, // Đã an toàn vì HTML cha đã bị khóa height
                    cutout: '65%',
                    plugins: {
                        legend: {
                            position: 'right',
                            labels: { font: { family: "'Segoe UI', sans-serif", size: 13 }, padding: 15 }
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) { return ' ' + context.label; }
                            }
                        }
                    }
                }
            });
        }


document.getElementById('statisticsRefresh').addEventListener('click',loadAllStatistics);
document.addEventListener('staff-session-ready',loadAllStatistics);document.addEventListener('staff-session-locked',clear);window.addEventListener('pagehide',clear);if(window.staffSessionReady)loadAllStatistics();
})();
