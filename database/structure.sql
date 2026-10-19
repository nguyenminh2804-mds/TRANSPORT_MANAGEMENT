-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Máy chủ: 127.0.0.1
-- Thời gian đã tạo: Th10 13, 2026 lúc 11:45 AM
-- Phiên bản máy phục vụ: 10.4.32-MariaDB
-- Phiên bản PHP: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Cơ sở dữ liệu: `transport_db`
--

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `assignments`
--

CREATE TABLE `assignments` (
  `assignment_id` bigint(20) UNSIGNED NOT NULL COMMENT 'Khóa chính của bản ghi phân công',
  `trip_id` bigint(20) UNSIGNED NOT NULL COMMENT 'TODO: FK logic tới trips.<PK>; chưa tạo FK cứng',
  `driver_id` bigint(20) UNSIGNED NOT NULL COMMENT 'FK nội bộ tới drivers.driver_id',
  `vehicle_id` bigint(20) UNSIGNED NOT NULL COMMENT 'FK nội bộ tới vehicles.vehicle_id',
  `assigned_by` bigint(20) UNSIGNED DEFAULT NULL COMMENT 'TODO: FK logic tới users.<PK> của tài khoản nhân viên điều phối',
  `assigned_at` datetime NOT NULL DEFAULT current_timestamp() COMMENT 'Thời điểm phân công',
  `completed_at` datetime DEFAULT NULL COMMENT 'Thời điểm hoàn thành phân công; dùng khi status=completed',
  `cancelled_at` datetime DEFAULT NULL COMMENT 'Thời điểm hủy phân công; dùng khi status=cancelled',
  `notes` text DEFAULT NULL COMMENT 'Ghi chú của nhân viên điều phối',
  `status` enum('assigned','completed','cancelled') NOT NULL DEFAULT 'assigned' COMMENT 'Trạng thái của PHÂN CÔNG, không phải trạng thái Order',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp() COMMENT 'Thời điểm tạo bản ghi',
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp() COMMENT 'Thời điểm cập nhật bản ghi gần nhất',
  `active_driver_id` bigint(20) UNSIGNED GENERATED ALWAYS AS (case when `status` = 'assigned' then `driver_id` else NULL end) STORED COMMENT 'Cột kỹ thuật: driver_id khi assignment đang active',
  `active_vehicle_id` bigint(20) UNSIGNED GENERATED ALWAYS AS (case when `status` = 'assigned' then `vehicle_id` else NULL end) STORED COMMENT 'Cột kỹ thuật: vehicle_id khi assignment đang active',
  `active_trip_id` bigint(20) UNSIGNED GENERATED ALWAYS AS (case when `status` = 'assigned' then `trip_id` else NULL end) STORED COMMENT 'Cột kỹ thuật: trip_id khi assignment đang active',
  `accepted_at` datetime DEFAULT NULL
) ;

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `assignment_requests`
--

CREATE TABLE `assignment_requests` (
  `request_id` bigint(20) UNSIGNED NOT NULL,
  `assignment_id` bigint(20) UNSIGNED NOT NULL,
  `driver_id` bigint(20) UNSIGNED NOT NULL,
  `request_type` enum('decline_assignment','cancel_assignment','cancel_trip') NOT NULL,
  `reason` text NOT NULL,
  `request_status` enum('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  `requested_at` datetime NOT NULL DEFAULT current_timestamp(),
  `reviewed_by` int(11) DEFAULT NULL,
  `reviewed_at` datetime DEFAULT NULL,
  `review_reason` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `customers`
--

CREATE TABLE `customers` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `name` varchar(100) NOT NULL,
  `phone` varchar(20) NOT NULL,
  `email` varchar(100) DEFAULT NULL,
  `address` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `drivers`
--

CREATE TABLE `drivers` (
  `driver_id` bigint(20) UNSIGNED NOT NULL COMMENT 'Khóa chính nội bộ của tài xế',
  `user_id` bigint(20) UNSIGNED DEFAULT NULL COMMENT 'TODO: FK logic tới users.<PK>; NULL khi chưa cấp tài khoản',
  `driver_code` varchar(50) NOT NULL COMMENT 'Mã tài xế, không được trùng',
  `full_name` varchar(150) NOT NULL COMMENT 'Họ và tên tài xế',
  `phone` varchar(20) NOT NULL COMMENT 'Số điện thoại liên hệ',
  `address` varchar(255) DEFAULT NULL COMMENT 'Địa chỉ của tài xế',
  `license_number` varchar(50) NOT NULL COMMENT 'Số bằng lái, không được trùng',
  `license_class` varchar(30) NOT NULL COMMENT 'Hạng bằng lái, ví dụ B, C, D...',
  `license_expiry` date DEFAULT NULL COMMENT 'Ngày hết hạn bằng lái; NULL nếu nghiệp vụ cho phép chưa có ngày',
  `status` enum('available','on_trip','inactive') NOT NULL DEFAULT 'available' COMMENT 'Trạng thái làm việc: available, on_trip, inactive',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp() COMMENT 'Thời điểm tạo hồ sơ',
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp() COMMENT 'Thời điểm cập nhật hồ sơ gần nhất'
) ;

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `driver_status_corrections`
--

CREATE TABLE `driver_status_corrections` (
  `correction_id` bigint(20) UNSIGNED NOT NULL,
  `driver_id` bigint(20) UNSIGNED NOT NULL,
  `old_status` varchar(30) NOT NULL,
  `new_status` varchar(30) NOT NULL,
  `reason` text NOT NULL,
  `changed_by` int(11) NOT NULL,
  `changed_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `orders`
--

CREATE TABLE `orders` (
  `id` int(11) NOT NULL,
  `order_code` varchar(50) NOT NULL,
  `customer_id` int(11) NOT NULL,
  `pickup_address` varchar(255) NOT NULL,
  `delivery_address` varchar(255) NOT NULL,
  `total_weight` decimal(10,2) DEFAULT 0.00,
  `shipping_fee` decimal(12,2) DEFAULT 0.00,
  `payment_status` enum('UNPAID','PAID') DEFAULT 'UNPAID',
  `status` enum('PENDING','CONFIRMED','PICKING_UP','IN_TRANSIT','DELIVERED','CANCELLED') DEFAULT 'PENDING',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `order_items`
--

CREATE TABLE `order_items` (
  `id` int(11) NOT NULL,
  `order_id` int(11) NOT NULL,
  `product_name` varchar(150) NOT NULL,
  `quantity` int(11) NOT NULL DEFAULT 1,
  `weight` decimal(10,2) DEFAULT 0.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `order_status_history`
--

CREATE TABLE `order_status_history` (
  `id` int(11) NOT NULL,
  `order_id` int(11) NOT NULL,
  `old_status` varchar(30) DEFAULT NULL,
  `new_status` varchar(30) NOT NULL,
  `changed_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `note` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `trips`
--

CREATE TABLE `trips` (
  `id` int(11) NOT NULL,
  `driver_id` bigint(20) UNSIGNED NOT NULL,
  `vehicle_id` bigint(20) UNSIGNED NOT NULL,
  `start_location` varchar(255) NOT NULL,
  `end_location` varchar(255) NOT NULL,
  `start_time` datetime DEFAULT NULL,
  `end_time` datetime DEFAULT NULL,
  `status` enum('PLANNED','IN_PROGRESS','COMPLETED','CANCELLED') DEFAULT 'PLANNED'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `trip_orders`
--

CREATE TABLE `trip_orders` (
  `id` int(11) NOT NULL,
  `trip_id` int(11) NOT NULL,
  `order_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `role` enum('ADMIN','STAFF','DRIVER','CUSTOMER') NOT NULL DEFAULT 'STAFF',
  `status` tinyint(4) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `vehicles`
--

CREATE TABLE `vehicles` (
  `vehicle_id` bigint(20) UNSIGNED NOT NULL COMMENT 'Khóa chính nội bộ của phương tiện',
  `license_plate` varchar(30) NOT NULL COMMENT 'Biển số xe đã được backend chuẩn hóa trước khi lưu, không trùng',
  `vehicle_type` varchar(100) NOT NULL COMMENT 'Loại xe',
  `brand` varchar(100) DEFAULT NULL COMMENT 'Hãng/nhãn hiệu phương tiện',
  `capacity` decimal(10,2) NOT NULL COMMENT 'TẢI TRỌNG ĐƠN VỊ KG (kilogram), phải lớn hơn 0',
  `status` enum('available','on_trip','maintenance','inactive') NOT NULL DEFAULT 'available' COMMENT 'Trạng thái: available, on_trip, maintenance, inactive',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp() COMMENT 'Thời điểm tạo hồ sơ',
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp() COMMENT 'Thời điểm cập nhật hồ sơ gần nhất'
) ;

--
-- Chỉ mục cho các bảng đã đổ
--

--
-- Chỉ mục cho bảng `assignments`
--
ALTER TABLE `assignments`
  ADD PRIMARY KEY (`assignment_id`),
  ADD UNIQUE KEY `uq_assignments_active_driver` (`active_driver_id`),
  ADD UNIQUE KEY `uq_assignments_active_vehicle` (`active_vehicle_id`),
  ADD UNIQUE KEY `uq_assignments_active_trip` (`active_trip_id`),
  ADD KEY `idx_assignments_trip_id` (`trip_id`),
  ADD KEY `idx_assignments_driver_id` (`driver_id`),
  ADD KEY `idx_assignments_vehicle_id` (`vehicle_id`),
  ADD KEY `idx_assignments_assigned_by` (`assigned_by`),
  ADD KEY `idx_assignments_status` (`status`),
  ADD KEY `idx_assignments_assigned_at` (`assigned_at`);

--
-- Chỉ mục cho bảng `assignment_requests`
--
ALTER TABLE `assignment_requests`
  ADD PRIMARY KEY (`request_id`),
  ADD KEY `idx_assignment_requests_assignment_status` (`assignment_id`,`request_status`),
  ADD KEY `idx_assignment_requests_driver_status` (`driver_id`,`request_status`),
  ADD KEY `idx_assignment_requests_reviewed_by` (`reviewed_by`);

--
-- Chỉ mục cho bảng `customers`
--
ALTER TABLE `customers`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_customers_user_id` (`user_id`);

--
-- Chỉ mục cho bảng `drivers`
--
ALTER TABLE `drivers`
  ADD PRIMARY KEY (`driver_id`),
  ADD UNIQUE KEY `uq_drivers_driver_code` (`driver_code`),
  ADD UNIQUE KEY `uq_drivers_license_number` (`license_number`),
  ADD UNIQUE KEY `uq_drivers_user_id` (`user_id`),
  ADD KEY `idx_drivers_status` (`status`),
  ADD KEY `idx_drivers_full_name` (`full_name`),
  ADD KEY `idx_drivers_phone` (`phone`),
  ADD KEY `idx_drivers_license_expiry` (`license_expiry`);

--
-- Chỉ mục cho bảng `driver_status_corrections`
--
ALTER TABLE `driver_status_corrections`
  ADD PRIMARY KEY (`correction_id`),
  ADD KEY `idx_driver_status_corrections_driver_date` (`driver_id`,`changed_at`),
  ADD KEY `idx_driver_status_corrections_changed_by` (`changed_by`);

--
-- Chỉ mục cho bảng `orders`
--
ALTER TABLE `orders`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `order_code` (`order_code`),
  ADD KEY `customer_id` (`customer_id`);

--
-- Chỉ mục cho bảng `order_items`
--
ALTER TABLE `order_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `order_id` (`order_id`);

--
-- Chỉ mục cho bảng `order_status_history`
--
ALTER TABLE `order_status_history`
  ADD PRIMARY KEY (`id`),
  ADD KEY `order_id` (`order_id`);

--
-- Chỉ mục cho bảng `trips`
--
ALTER TABLE `trips`
  ADD PRIMARY KEY (`id`),
  ADD KEY `driver_id` (`driver_id`),
  ADD KEY `vehicle_id` (`vehicle_id`);

--
-- Chỉ mục cho bảng `trip_orders`
--
ALTER TABLE `trip_orders`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `trip_id` (`trip_id`,`order_id`),
  ADD KEY `order_id` (`order_id`);

--
-- Chỉ mục cho bảng `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`);

--
-- Chỉ mục cho bảng `vehicles`
--
ALTER TABLE `vehicles`
  ADD PRIMARY KEY (`vehicle_id`),
  ADD UNIQUE KEY `uq_vehicles_license_plate` (`license_plate`),
  ADD KEY `idx_vehicles_status` (`status`),
  ADD KEY `idx_vehicles_type` (`vehicle_type`),
  ADD KEY `idx_vehicles_capacity` (`capacity`);

--
-- AUTO_INCREMENT cho các bảng đã đổ
--

--
-- AUTO_INCREMENT cho bảng `assignments`
--
ALTER TABLE `assignments`
  MODIFY `assignment_id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'Khóa chính của bản ghi phân công';

--
-- AUTO_INCREMENT cho bảng `assignment_requests`
--
ALTER TABLE `assignment_requests`
  MODIFY `request_id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT cho bảng `customers`
--
ALTER TABLE `customers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT cho bảng `drivers`
--
ALTER TABLE `drivers`
  MODIFY `driver_id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'Khóa chính nội bộ của tài xế';

--
-- AUTO_INCREMENT cho bảng `driver_status_corrections`
--
ALTER TABLE `driver_status_corrections`
  MODIFY `correction_id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT cho bảng `orders`
--
ALTER TABLE `orders`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT cho bảng `order_items`
--
ALTER TABLE `order_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT cho bảng `order_status_history`
--
ALTER TABLE `order_status_history`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT cho bảng `trips`
--
ALTER TABLE `trips`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT cho bảng `trip_orders`
--
ALTER TABLE `trip_orders`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT cho bảng `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT cho bảng `vehicles`
--
ALTER TABLE `vehicles`
  MODIFY `vehicle_id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'Khóa chính nội bộ của phương tiện';

--
-- Các ràng buộc cho các bảng đã đổ
--

--
-- Các ràng buộc cho bảng `assignments`
--
ALTER TABLE `assignments`
  ADD CONSTRAINT `fk_assignments_driver` FOREIGN KEY (`driver_id`) REFERENCES `drivers` (`driver_id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_assignments_vehicle` FOREIGN KEY (`vehicle_id`) REFERENCES `vehicles` (`vehicle_id`) ON UPDATE CASCADE;

--
-- Các ràng buộc cho bảng `assignment_requests`
--
ALTER TABLE `assignment_requests`
  ADD CONSTRAINT `fk_assignment_requests_assignment` FOREIGN KEY (`assignment_id`) REFERENCES `assignments` (`assignment_id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_assignment_requests_driver` FOREIGN KEY (`driver_id`) REFERENCES `drivers` (`driver_id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_assignment_requests_reviewer` FOREIGN KEY (`reviewed_by`) REFERENCES `users` (`id`) ON UPDATE CASCADE;

--
-- Các ràng buộc cho bảng `customers`
--
ALTER TABLE `customers`
  ADD CONSTRAINT `fk_customers_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Các ràng buộc cho bảng `driver_status_corrections`
--
ALTER TABLE `driver_status_corrections`
  ADD CONSTRAINT `fk_driver_status_corrections_driver` FOREIGN KEY (`driver_id`) REFERENCES `drivers` (`driver_id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_driver_status_corrections_user` FOREIGN KEY (`changed_by`) REFERENCES `users` (`id`) ON UPDATE CASCADE;

--
-- Các ràng buộc cho bảng `orders`
--
ALTER TABLE `orders`
  ADD CONSTRAINT `orders_ibfk_1` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`);

--
-- Các ràng buộc cho bảng `order_items`
--
ALTER TABLE `order_items`
  ADD CONSTRAINT `order_items_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE;

--
-- Các ràng buộc cho bảng `order_status_history`
--
ALTER TABLE `order_status_history`
  ADD CONSTRAINT `order_status_history_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE;

--
-- Các ràng buộc cho bảng `trips`
--
ALTER TABLE `trips`
  ADD CONSTRAINT `trips_fk_driver_v2` FOREIGN KEY (`driver_id`) REFERENCES `drivers` (`driver_id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `trips_fk_vehicle_v2` FOREIGN KEY (`vehicle_id`) REFERENCES `vehicles` (`vehicle_id`) ON UPDATE CASCADE;

--
-- Các ràng buộc cho bảng `trip_orders`
--
ALTER TABLE `trip_orders`
  ADD CONSTRAINT `trip_orders_ibfk_1` FOREIGN KEY (`trip_id`) REFERENCES `trips` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `trip_orders_ibfk_2` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
