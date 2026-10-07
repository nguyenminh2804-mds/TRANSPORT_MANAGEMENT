# Chạy và test Quản lý tài xế — full v2

## Database chính và cài đặt
File chính của project là [transport_management.sql](transport_management.sql), sao chép nguyên nội dung transport_db_merged_full_v2.sql do nhóm cung cấp. Đây là bản đầy đủ **10 bảng**, dùng để khởi tạo **transport_db trống**. File có CREATE DATABASE/USE transport_db, các CREATE TABLE không có IF NOT EXISTS và một số dữ liệu mẫu CUSTOMER/Orders. Không dùng để nâng cấp hoặc import đè lên database đã có bảng.
Database trên máy đã import thành công: **không chạy lại SQL**. Các file transport_db_merged_full_v2.sql và transport_db_v2.sql được giữ để đối chiếu; v2 cũ chỉ có ba bảng, không phải bản đầy đủ.
**drivers_migration.sql là migration cũ, không áp dụng cho schema v2/full v2.** Hiện file này không có trong thư mục; nếu có bản sao cũ thì không chạy. Không cần migration cho các cột Driver đang dùng.

## Chạy trên XAMPP
1. Bật Apache/MySQL; kiểm tra backend/config/database.php trỏ tới transport_db đã import.
2. Mở http://localhost/TRANSPORT_MANAGEMENT/frontend/customer/login.html, đổi cổng theo Apache.
3. Dùng tài khoản ADMIN/STAFF đang hoạt động (users.status=1) do module tài khoản cấp.
4. Sau đăng nhập mở http://localhost/TRANSPORT_MANAGEMENT/frontend/transport/drivers.html trên cùng origin/cổng.
Auth.js và drivers.js tự tính URL API theo đường dẫn trang, không cố định cổng 19160.
users đã tồn tại và có schema tương thích User/AuthController. STAFF đã được người dùng cấp và đăng nhập thành công; dữ liệu seed của file SQL vẫn chỉ tạo CUSTOMER. Chỉ tạo tài khoản DRIVER qua chức năng Thêm tài xế được STAFF/ADMIN yêu cầu; không đổi quyền hoặc đoán mật khẩu tài khoản có sẵn. Trang vẫn từ chối tài khoản thiếu quyền.

## API
URL: backend/public/index.php?route=<route>&id=<driver_id>. Query id giữ nguyên để tương thích routes; response dùng driver_id.
- GET /api/drivers: search (mã/họ tên/điện thoại/GPLX), status available/on_trip/inactive.
- GET /api/drivers/show&id=1.
- POST /api/drivers.
- PUT /api/drivers&id=1: gửi đầy đủ hồ sơ.
- DELETE /api/drivers&id=1.
- PUT /api/drivers/status&id=1: {"status":"inactive"}.

Body sửa hồ sơ (PUT):
```json
{"driver_code":"TX001","full_name":"Nguyễn Văn A","phone":"0901234567","address":null,"license_number":"123456789012","license_class":"C","license_expiry":"2028-12-31","status":"available","user_id":null}
```

Mã bắt buộc tối đa 50; họ tên 150; GPLX 50; hạng 30 sau viết hoa, không khóa danh sách; địa chỉ 255 hoặc null. Phone lưu VARCHAR(20), validation nghiệp vụ 9–15 chữ số và dấu + tùy chọn. license_expiry null hoặc ngày YYYY-MM-DD hợp lệ; có thể lưu hồ sơ GPLX hết hạn, chưa làm logic phân công.
ID trả dạng chuỗi để giữ độ chính xác BIGINT UNSIGNED. user_id nhận chuỗi số nguyên dương/integer hoặc null. Tài khoản liên kết phải có quyền DRIVER và hoạt động. Mã/GPLX/user_id trùng bị UNIQUE chặn.
Response có created_at, updated_at, has_active_assignment và has_active_trip. API trả {success,message,data}, lỗi trả {success:false,message}. HTTP: 400 JSON sai; 401 chưa đăng nhập; 403 thiếu quyền/khóa; 404 không tìm thấy; 409 trùng/liên quan; 422 validation; 503 thiếu bảng tích hợp; 500 lỗi server/database.

## Bảo vệ lịch sử
- Mọi assignment assigned/completed/cancelled chặn xóa; mọi lịch sử trips trực tiếp cũng chặn xóa. on_trip không được xóa.
- Assignment assigned hoặc trips PLANNED/IN_PROGRESS chặn đổi sang trạng thái khác qua cả form Sửa và API trạng thái.
- Vẫn sửa được hồ sơ nếu giữ nguyên trạng thái. PUT cùng trạng thái không phải đổi trạng thái.
- Form khóa chọn trạng thái theo các cờ hiệu lực. Backend khóa drivers/assignments/trips, kiểm tra lại trong transaction trước khi ghi Driver. FK từ trips/assignments bảo vệ thêm khi xóa.
- Không ghi assignments/vehicles/trips; không tạo giao diện/API phân công hoặc phương tiện. Chưa quyết định cơ chế đồng bộ trips và assignments của module phân công sau này.
- users.id vẫn INT signed, drivers.user_id BIGINT UNSIGNED; chưa có FK này và không tự đổi kiểu bảng Users. Backend xác minh tài khoản và UNIQUE giữ một user cho một hồ sơ.

## Kiểm thử tự động không ghi database
```text
node tests/driver-api.test.cjs
node tests/driver-api.test.cjs --http
```
Lệnh đầu kiểm tra validation, lịch sử/trạng thái và giao dịch CRUD với connection giả trong bộ nhớ; SELECT model trên database hiện có; controller qua users thật; frontend bằng DOM/fetch giả; kiểm tra file SQL chính khớp bản nguồn và đủ 10 bảng. Không tạo database thử, không DDL, không fixture thật, không ghi MySQL.
Lệnh --http cần Apache; mặc định http://localhost/TRANSPORT_MANAGEMENT. Có thể đặt TRANSPORT_TEST_BASE_URL sang origin/cổng/path đang chạy. Chỉ gọi các API với trạng thái chưa đăng nhập hoặc thông tin đăng nhập không hợp lệ, không cho phép CRUD ghi database.
Phiên trong controller unit test chỉ tồn tại trong process test, không tạo phiên trình duyệt và không bỏ kiểm tra tài khoản thật. CUSTOMER vẫn bị từ chối kể cả role ADMIN giả trong session. Nếu có manager hoạt động sau này, test chỉ đọc danh sách, không tự ghi dữ liệu.

## Test thủ công sau khi có tài khoản quản lý
1. Danh sách: đăng nhập ADMIN/STAFF; mở trang, kiểm tra danh sách trống hoặc hồ sơ hiện có; không dùng CUSTOMER để vượt quyền.
2. Tìm kiếm: mã, tên tiếng Việt, điện thoại/GPLX, chuỗi không tồn tại, kết hợp từng trạng thái và Xóa bộ lọc. Dấu %/_ được hiểu như văn bản, không phải wildcard nhập vào.
3. Thêm: nhập mẫu JSON qua form, để user_id trống; Lưu đóng modal và cập nhật bảng. Xóa bộ lọc nếu hồ sơ mới không phù hợp bộ lọc đang chọn.
4. Xem: kiểm tra mã/họ tên/địa chỉ/điện thoại/GPLX/hạng/hạn/trạng thái/user_id; form chỉ đọc.
5. Sửa: đổi họ tên, địa chỉ, hạng, hạn hoặc mã; lưu, mở Xem lại và kiểm tra không reload trang.
6. Xóa: hồ sơ chưa có phân công/chuyến và không on_trip; thử hủy rồi xác nhận. Với lịch sử assignment hoặc trips phải lỗi 409 và dữ liệu được giữ.
7. Trạng thái: đổi available/on_trip/inactive khi không còn dữ liệu hiệu lực; khi còn assigned hoặc trips PLANNED/IN_PROGRESS phải bị chặn ở nút Trạng thái và form Sửa. Hồ sơ có lịch sử completed/cancelled vẫn không xóa, nhưng có thể đổi trạng thái.
Chỉ tạo/sửa dữ liệu thử khi người phụ trách database cho phép; không tự tạo assignments/vehicles/trips để test.

## Validation và lỗi nên thử
Trường bắt buộc rỗng/chỉ dấu cách; vượt độ dài; điện thoại có chữ; mã/GPLX/user_id trùng; license_expiry sai định dạng hoặc ngày không tồn tại (2027-02-29); status viết hoa hoặc không hợp lệ; ID âm/thập phân/vượt BIGINT; user_id không tồn tại/sai quyền/đã khóa; JSON hỏng/array hoặc trường sai kiểu.
Tên chứa HTML phải hiển thị văn bản, không thực thi thẻ. CUSTOMER/DRIVER và tài khoản khóa bị từ chối; chưa đăng nhập 401. Thiếu bảng tích hợp trả 503, không bỏ xác minh tài khoản.

## Giới hạn kết quả kiểm thử
Đã có users/trips, không còn bị chặn bởi thiếu hai bảng này. STAFF hiện đã tồn tại; controller được kiểm tra đọc danh sách bằng tài khoản quản lý thật trong process test. Công cụ chưa kết nối được trình duyệt nên chưa kiểm thử GUI/HTTP có cookie authenticated hoặc CRUD ghi database thành công bằng mật khẩu nhập trên giao diện. CRUD/rollback đã kiểm tra bằng connection giả; không đồng nghĩa đã kiểm chứng giao dịch MySQL có ghi hay tranh chấp đồng thời. Bố cục frontend chưa được xác nhận bằng thao tác trên trình duyệt.
Dừng ở Quản lý tài xế; chờ người dùng test OK trước chức năng tiếp theo.

## Dashboard STAFF mới
Mở /frontend/transport/dashboard.html sau đăng nhập; nhấn Ctrl+F5. Trang tái sử dụng drivers.css cho header/card/button và transport-dashboard.css có phạm vi riêng. Có menu/thẻ Quản lý tài xế dẫn tới drivers.html; không có link chức năng Vehicle/Assignment chưa triển khai.
Số liệu tổng/rảnh/đang chuyến/ngừng việc đọc từ /api/drivers sau /api/me xác minh quyền ADMIN/STAFF.
Thử: login STAFF -> dashboard có giao diện và tên tài khoản -> bấm Quản lý tài xế -> danh sách/form tải đúng -> reload -> quay về Tổng quan. Không thay host/cổng. Các CSS/JS của hai trang đã được kiểm tra HTTP 200, không 404; không có lỗi PHP mới trong log.
Kiểm thử GUI và thao tác ghi dữ liệu thật vẫn cần người dùng tự thực hiện; chưa coi mô phỏng là kiểm thử MySQL có ghi. Không tạo dữ liệu, tài khoản hoặc database thử trong lần hoàn thiện dashboard này.


## Tạo đồng thời hồ sơ và tài khoản DRIVER
POST /api/drivers gửi các trường hồ sơ cùng `username` và `password`. Không gửi `user_id`; backend tạo tài khoản `role=DRIVER`, `status=1`, hash mật khẩu bằng PASSWORD_DEFAULT và dùng ID mới để tạo hồ sơ. Mã tài xế vẫn bắt buộc theo schema. Họ tên tạo mới tối đa 100 ký tự (users.full_name); PUT vẫn giữ giới hạn drivers.full_name 150. Tên đăng nhập bắt buộc, tối đa 50 ký tự và được kiểm tra theo collation/UNIQUE hiện có. Mật khẩu theo quy tắc không rỗng của ứng dụng, tối đa 72 byte UTF-8 để tránh bcrypt cắt ngắn; giữ nguyên khoảng trắng đầu/cuối nếu mật khẩu không chỉ có khoảng trắng.

Hai INSERT dùng cùng PDO và một transaction. Mọi lỗi INSERT hoặc đọc lại hồ sơ đều rollback. Response chỉ chứa hồ sơ Driver; không có username, mật khẩu hoặc hash. Sửa hồ sơ không gửi/đổi thông tin đăng nhập. Hồ sơ cũ có thể liên kết tài khoản DRIVER đang hoạt động qua `user_id` trong PUT như trước.

Kiểm tra:
- `node tests/driver-api.test.cjs --http`: validation, transaction mô phỏng, frontend thêm tài khoản/đổi chế độ form, tên đăng nhập trùng, lỗi cạnh trường, mật khẩu UTF-8 và hồi quy CRUD/quyền.
- `C:/xampp/php/php.exe tests/driver-account.test.php --mysql`: chạy SQL thật trên MySQL nhưng chặn COMMIT thành công để kiểm tra cặp bản ghi trong transaction rồi rollback. Kiểm tra username trùng (kể cả khác hoa/thường), hồ sơ trùng và lỗi sau INSERT users/sau cả hai INSERT. Không có DDL hoặc bản ghi thử được lưu; AUTO_INCREMENT có thể tăng dù rollback. Không chạy lại migration/SQL.

Chế độ MySQL không xác nhận lưu bền vững sau COMMIT hoặc chuỗi đăng nhập qua HTTP bằng tài khoản mới. Để kiểm tra thủ công: STAFF thêm hồ sơ+tài khoản, thấy hồ sơ mới, rồi mở phiên trình duyệt riêng và đăng nhập DRIVER bằng mật khẩu vừa khởi tạo. Không gửi mật khẩu/cookie vào log hoặc chat.
