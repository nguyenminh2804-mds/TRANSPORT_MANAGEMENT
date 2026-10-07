# Kiểm tra Quản lý phương tiện

Schema đã đối chiếu trực tiếp MySQL transport_db: vehicles v2, tải trọng kg, FK từ trips và assignments. Không import SQL.

- `C:/xampp/php/php.exe tests/vehicle.test.php`: validation, BIGINT, trùng biển số cũ/mới, CRUD và rollback trong bộ nhớ.
- `C:/xampp/php/php.exe tests/vehicle.test.php --read-only-mysql`: thêm SELECT danh sách, thống kê, tìm kiếm/lọc/chi tiết và quyền theo tài khoản thật; không DDL/DML.
- `node tests/vehicle-ui.test.cjs`: API/DOM giả lập; không ghi MySQL.

Mở http://localhost/TRANSPORT_MANAGEMENT/frontend/transport/vehicles.html (hoặc cùng đường dẫn trên cổng Apache thực tế). Đăng nhập STAFF/ADMIN bằng phiên PHP hiện có. Thử desktop và 375px: 5 thẻ thống kê, tìm kiếm/lọc/làm mới, bảng/thẻ, modal xem/thêm/sửa, xác nhận xóa, lỗi cạnh trường.

Database đang có dữ liệu thật: KHÔNG thử POST/PUT/DELETE với phiên có quyền ở database này. CRUD thực qua HTTP chỉ xác nhận khi nhóm cung cấp môi trường kiểm thử riêng. Không chạy test ghi rồi rollback ở database thật.

Biển số viết hoa, bỏ toàn bộ khoảng trắng; giữ dấu chấm/gạch ngang. Bản ghi cũ được so sánh theo cùng quy tắc khi kiểm tra trùng, không tự sửa dữ liệu cũ. Tải trọng >0, tối đa 99999999.99 kg, 2 chữ số thập phân.

Mọi lịch sử trips/assignments chặn xóa, kể cả đã hoàn tất/hủy. Chuyến PLANNED/IN_PROGRESS hoặc assignment assigned chặn đổi trạng thái; sửa hồ sơ giữ nguyên trạng thái vẫn được phép. Không viết trips/assignments, không triển khai phân công. Nhóm cần xác nhận quy trình trạng thái thủ công khi không còn tham chiếu hoạt động; hiện giữ cách xử lý của Driver, không tự suy ra hay đồng bộ trạng thái.
