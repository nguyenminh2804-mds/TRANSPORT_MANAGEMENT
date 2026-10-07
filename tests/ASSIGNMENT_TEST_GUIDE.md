# Assignment

Nguồn hiệu lực: assignments.status=assigned. trips.driver_id/vehicle_id là cặp gần nhất, cập nhật cùng transaction tạo/đổi; giữ sau hoàn tất/hủy. Không đổi trips.status hoặc orders. Không DELETE lịch sử.

Tạo/đổi chỉ chọn chuyến PLANNED/IN_PROGRESS; tài xế/xe available, hoặc chính tài nguyên on_trip của bản đang đổi. GPLX đã hết hạn không được chọn; ngày NULL giữ quy tắc hồ sơ hiện có. Backend kiểm tra lại dưới khóa; UNIQUE active_driver/vehicle/trip chặn tranh chấp. Mã chuyến tối đa 2147483647; các BIGINT đi qua JSON dạng chuỗi.

Đổi có thể chọn chuyến khác; khóa cả hai chuyến theo thứ tự, hủy bản cũ và tạo bản mới. Chuyến cũ giữ cặp gần nhất. assigned_by lấy session, không lấy frontend. Các bước lỗi rollback toàn bộ. Hoàn tất/hủy chỉ trả on_trip về available khi không còn assigned; không ghi đè inactive/maintenance.

Chỉ dùng mock/unit để ghi: `C:/xampp/php/php.exe tests/assignment.test.php`; `node tests/assignment-ui.test.cjs`. Hồi quy: `node tests/driver-api.test.cjs`, `C:/xampp/php/php.exe tests/vehicle.test.php`, `node tests/vehicle-ui.test.cjs`, `node tests/transport-navigation.test.cjs`.

Không INSERT/UPDATE/DELETE database transport_db thật, kể cả rollback. Không tạo database thử/import SQL. Tranh chấp đồng thời và transaction MySQL có ghi chưa xác nhận; cần môi trường kiểm thử do nhóm cung cấp.

URL: http://localhost/TRANSPORT_MANAGEMENT/frontend/transport/assignment.html. Trang tái dùng CSS responsive Driver và thanh điều hướng chung. Kiểm tra trực quan desktop/mobile và chuỗi login bằng tài khoản thật chưa được coi là đạt nếu chỉ kiểm tra mock/HTTP 200.
