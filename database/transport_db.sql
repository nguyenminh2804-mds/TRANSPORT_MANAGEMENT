-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Máy chủ: 127.0.0.1
-- Thời gian đã tạo: Th10 21, 2026 lúc 08:11 AM
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
  `accepted_at` datetime DEFAULT NULL,
  `actual_started_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Đang đổ dữ liệu cho bảng `assignments`
--

INSERT INTO `assignments` (`assignment_id`, `trip_id`, `driver_id`, `vehicle_id`, `assigned_by`, `assigned_at`, `completed_at`, `cancelled_at`, `notes`, `status`, `created_at`, `updated_at`, `accepted_at`, `actual_started_at`) VALUES
(11, 10, 7, 11, 16, '2026-10-20 19:45:16', NULL, '2026-10-20 19:45:38', 'Tài xế 03\n51C-333.33\nHà Nội -> Ninh Bình', 'cancelled', '2026-10-20 12:45:16', '2026-10-20 12:45:38', NULL, NULL),
(12, 10, 7, 11, 16, '2026-10-20 19:45:38', NULL, '2026-10-20 19:45:57', 'Tài xế 03/51C-333.33/Hà Nội -> Ninh Bình\n[Hủy bởi tài khoản #16] Tài xế bận', 'cancelled', '2026-10-20 12:45:38', '2026-10-20 12:45:57', NULL, NULL),
(13, 10, 7, 11, 16, '2026-10-20 19:46:16', NULL, '2026-10-20 19:46:33', 'Tài xế 03 thực hiện chuyến', 'cancelled', '2026-10-20 12:46:16', '2026-10-20 12:46:33', NULL, NULL),
(14, 10, 8, 10, 16, '2026-10-20 19:46:33', NULL, '2026-10-20 19:56:32', 'Tài xế 04 thực hiện chuyến', 'cancelled', '2026-10-20 12:46:33', '2026-10-20 12:56:32', NULL, NULL),
(15, 10, 8, 10, 16, '2026-10-20 20:00:22', NULL, '2026-10-20 20:16:54', 'Tài xế 04 thực hiện chuyến', 'cancelled', '2026-10-20 13:00:22', '2026-10-20 13:16:54', '2026-10-20 20:01:13', NULL),
(16, 10, 8, 11, 16, '2026-10-20 20:26:18', '2026-10-20 20:26:50', NULL, 'Tài xế 04 thực hiện chuyến', 'completed', '2026-10-20 13:26:18', '2026-10-20 13:26:50', '2026-10-20 20:26:35', NULL),
(17, 11, 8, 10, 16, '2026-10-20 20:40:44', NULL, '2026-10-20 20:43:26', 'Tài xế 04 thực hiện chuyến\n[Thời điểm bắt đầu thực tế của lần phân công: 2026-10-20 20:41:29]', 'cancelled', '2026-10-20 13:40:44', '2026-10-20 19:29:32', '2026-10-20 20:40:57', NULL),
(18, 11, 8, 10, 16, '2026-10-20 20:44:06', NULL, '2026-10-20 20:46:07', 'Tài xế 04 thực hiện chuyến', 'cancelled', '2026-10-20 13:44:06', '2026-10-20 13:46:07', '2026-10-20 20:44:45', NULL),
(19, 11, 7, 10, 16, '2026-10-20 20:49:07', NULL, '2026-10-20 21:51:21', 'Tài xế 03 thực hiện chuyến\n[Hủy bởi tài khoản #16] Tài xế 03 bận', 'cancelled', '2026-10-20 13:49:07', '2026-10-20 14:51:21', NULL, NULL),
(20, 11, 7, 10, 16, '2026-10-20 22:01:31', NULL, '2026-10-20 22:57:08', '\n[Hủy bởi tài khoản #16] Tài xế 03 bận', 'cancelled', '2026-10-20 15:01:31', '2026-10-20 15:57:08', NULL, NULL),
(21, 11, 7, 10, 16, '2026-10-20 22:58:10', NULL, '2026-10-21 00:41:02', 'Tài xế 03 tiếp nhận chuyến\n[Hủy bởi tài khoản #16] Tài xế 03 bận', 'cancelled', '2026-10-20 15:58:10', '2026-10-20 17:41:02', NULL, NULL),
(22, 11, 7, 10, 16, '2026-10-21 02:29:32', NULL, '2026-10-21 02:29:49', 'Tài xế 03 thực hiện chuyến\n[Hủy bởi tài khoản #16] Tài xế 03 bận', 'cancelled', '2026-10-20 19:29:32', '2026-10-20 19:29:49', NULL, NULL),
(23, 11, 7, 10, 16, '2026-10-21 02:30:50', NULL, '2026-10-21 02:33:56', 'Tài xế 03 thực hiện chuyến\n[Hủy bởi tài khoản #16] tài xế 03 bận', 'cancelled', '2026-10-20 19:30:50', '2026-10-20 19:33:56', '2026-10-21 02:31:45', NULL),
(24, 11, 7, 10, 16, '2026-10-21 02:34:17', NULL, '2026-10-21 02:42:39', 'Tài xế 03 thực hiện chuyến\n[Thời điểm bắt đầu thực tế của lần phân công: 2026-10-21 02:41:13]', 'cancelled', '2026-10-20 19:34:17', '2026-10-20 19:42:39', '2026-10-21 02:36:25', NULL),
(25, 11, 7, 10, 16, '2026-10-21 03:11:05', NULL, '2026-10-21 03:11:41', 'Tài xế 03 thực hiện chuyến\n[Hủy bởi tài khoản #16] Tài xế 03 bận', 'cancelled', '2026-10-20 20:11:05', '2026-10-20 20:11:41', '2026-10-21 03:11:14', '2026-10-21 03:11:17'),
(26, 11, 7, 10, 16, '2026-10-21 03:12:23', NULL, '2026-10-21 03:12:48', 'Tài xế 03 thực hiện chuyến\n[Hủy bởi tài khoản #16] Tài xế 03 bận', 'cancelled', '2026-10-20 20:12:23', '2026-10-20 20:12:48', '2026-10-21 03:12:30', '2026-10-21 03:12:32'),
(27, 11, 7, 10, 16, '2026-10-21 03:13:32', NULL, '2026-10-21 03:13:58', 'Tài xế 03 thực hiện chuyến', 'cancelled', '2026-10-20 20:13:32', '2026-10-20 20:13:58', NULL, NULL),
(28, 11, 7, 10, 16, '2026-10-21 03:14:31', NULL, '2026-10-21 03:15:44', 'Tài xế 03 thực hiện chuyến\n[Hủy bởi tài khoản #16] Tài xế 03 bận', 'cancelled', '2026-10-20 20:14:31', '2026-10-20 20:15:44', '2026-10-21 03:14:37', '2026-10-21 03:15:01'),
(29, 11, 7, 10, 16, '2026-10-21 03:16:02', '2026-10-21 03:17:33', NULL, 'Tài xế 03 thực hiện chuyến', 'completed', '2026-10-20 20:16:02', '2026-10-20 20:17:33', '2026-10-21 03:16:34', '2026-10-21 03:17:02'),
(30, 14, 7, 11, 16, '2026-10-21 12:17:08', NULL, '2026-10-21 12:17:34', 'Tài xế 03 thực hiện chuyến', 'cancelled', '2026-10-21 05:17:08', '2026-10-21 05:17:34', NULL, NULL),
(31, 15, 7, 11, 16, '2026-10-21 12:17:34', NULL, '2026-10-21 12:18:51', 'Tài xế 03 thực hiện chuyến\n[Hủy bởi tài khoản #16] Nhân viên hủy hộ tài xế 03: bận', 'cancelled', '2026-10-21 05:17:34', '2026-10-21 05:18:51', NULL, NULL),
(32, 15, 7, 11, 16, '2026-10-21 12:20:00', NULL, '2026-10-21 12:21:52', 'Tài xế 03 thực hiện chuyến', 'cancelled', '2026-10-21 05:20:00', '2026-10-21 05:21:52', NULL, NULL),
(33, 15, 7, 11, 16, '2026-10-21 12:22:37', '2026-10-21 12:26:19', NULL, 'Tài xế 03 thực hiện chuyến', 'completed', '2026-10-21 05:22:37', '2026-10-21 05:26:19', '2026-10-21 12:23:37', '2026-10-21 12:24:54'),
(34, 14, 8, 10, 16, '2026-10-21 12:26:06', NULL, '2026-10-21 12:27:46', 'Tài xế 04 thực hiện chuyến\n[Hủy bởi tài khoản #16] Bận', 'cancelled', '2026-10-21 05:26:06', '2026-10-21 05:27:46', '2026-10-21 12:27:26', '2026-10-21 12:27:28'),
(35, 14, 8, 10, 16, '2026-10-21 12:28:30', '2026-10-21 12:31:01', NULL, 'Tài xế 04 thực hiện chuyến', 'completed', '2026-10-21 05:28:30', '2026-10-21 05:31:01', '2026-10-21 12:28:43', '2026-10-21 12:30:30'),
(36, 16, 7, 11, 16, '2026-10-21 12:33:33', '2026-10-21 12:38:26', NULL, 'Tài xế 03 thực hiện chuyến', 'completed', '2026-10-21 05:33:33', '2026-10-21 05:38:26', '2026-10-21 12:37:05', '2026-10-21 12:37:52');

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

--
-- Đang đổ dữ liệu cho bảng `assignment_requests`
--

INSERT INTO `assignment_requests` (`request_id`, `assignment_id`, `driver_id`, `request_type`, `reason`, `request_status`, `requested_at`, `reviewed_by`, `reviewed_at`, `review_reason`) VALUES
(1, 14, 8, 'decline_assignment', 'Tài xế 04 có việc đột xuất', 'rejected', '2026-10-20 19:48:29', 16, '2026-10-20 19:52:21', 'Tài xế 04 vẫn tiếp tục thực hiện chuyến'),
(2, 14, 8, 'decline_assignment', 'Tài xế 04 có việc đột xuất', 'approved', '2026-10-20 19:54:01', 16, '2026-10-20 19:56:32', 'Tài xế 04 yêu cầu hủy chuyến: việc đột xuất'),
(3, 15, 8, 'cancel_assignment', 'Tài xế 04 bận', 'rejected', '2026-10-20 20:09:34', 16, '2026-10-20 20:10:19', 'Tài xế 04 tiếp tục thực hiện chuyến'),
(4, 15, 8, 'cancel_trip', 'Tài xế 04 bận', 'rejected', '2026-10-20 20:15:42', 16, '2026-10-20 20:16:00', 'Tài xế 04 tiếp tục thực hiện chuyến'),
(5, 15, 8, 'cancel_trip', 'Tài xế bận', 'approved', '2026-10-20 20:16:27', 16, '2026-10-20 20:16:54', 'Tài xế 04 yêu cầu hủy chuyến: tài xế bận'),
(6, 17, 8, 'cancel_trip', 'Tài xế 04 bận', 'rejected', '2026-10-20 20:42:03', 16, '2026-10-20 20:42:47', 'Tài xế 04 tiếp tục thực hiện chuyến'),
(7, 17, 8, 'cancel_trip', 'Tài xế 04 bận', 'approved', '2026-10-20 20:43:11', 16, '2026-10-20 20:43:26', 'Tài xế 04 bận'),
(8, 18, 8, 'cancel_trip', 'Tài xế 04 bận', 'approved', '2026-10-20 20:45:54', 16, '2026-10-20 20:46:07', 'tài xế 04  bận'),
(9, 23, 7, 'decline_assignment', 'Tài xế 03 bận', 'rejected', '2026-10-21 02:31:20', 16, '2026-10-21 02:31:33', 'Tài xế 03 tiếp tục thực hiện chuyến'),
(10, 24, 7, 'cancel_assignment', 'Tài xế 03 bận', 'rejected', '2026-10-21 02:40:02', 16, '2026-10-21 02:40:23', 'Tài xế 03 tiếp tục thực hiện chuyến'),
(11, 24, 7, 'cancel_trip', 'tài xế 03 bận', 'approved', '2026-10-21 02:42:22', 16, '2026-10-21 02:42:39', 'tài xế 03 bận'),
(12, 27, 7, 'decline_assignment', 'Tài xế 03 bận', 'approved', '2026-10-21 03:13:48', 16, '2026-10-21 03:13:58', 'Tài xế 03 bận'),
(13, 32, 7, 'decline_assignment', 'Bận', 'rejected', '2026-10-21 12:21:06', 16, '2026-10-21 12:21:27', 'Tiếp tục chuyến'),
(14, 32, 7, 'decline_assignment', 'Bận', 'approved', '2026-10-21 12:21:35', 16, '2026-10-21 12:21:52', 'Duyệt');

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

--
-- Đang đổ dữ liệu cho bảng `customers`
--

INSERT INTO `customers` (`id`, `user_id`, `name`, `phone`, `email`, `address`, `created_at`) VALUES
(1, 1, 'Nguyen Van Test', '0901234567', 'customer01@gmail.com', 'Hà Nội', '2026-10-06 16:30:29'),
(2, 2, 'Tran Thi Mai', '0912345678', 'maitran@gmail.com', 'Hai Phong', '2026-10-07 05:38:04'),
(3, 3, 'Le Quang Huy', '0987654321', 'huy.le@gmail.com', 'Da Nang', '2026-10-07 05:38:04'),
(4, 4, 'Pham Ngoc Anh', '0938123456', 'anh.pham@gmail.com', 'Ho Chi Minh', '2026-10-07 05:38:04');

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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Đang đổ dữ liệu cho bảng `drivers`
--

INSERT INTO `drivers` (`driver_id`, `user_id`, `driver_code`, `full_name`, `phone`, `address`, `license_number`, `license_class`, `license_expiry`, `status`, `created_at`, `updated_at`) VALUES
(7, 23, 'TEST_TX003', 'Tài xế 03', '0901000003', 'Hà Nội', 'TEST_GPLX003', 'C', '2030-12-31', 'available', '2026-10-07 07:58:22', '2026-10-21 05:41:09'),
(8, 24, 'TEST_TX004', 'Tài xế 04', '0901000004', 'Hà Nội', 'TEST_GPLX004', 'C', '2030-12-31', 'available', '2026-10-07 07:58:22', '2026-10-21 05:31:01'),
(19, 38, 'TEST_TX005', 'Tài xế 05', '0222222586', 'Hà Nội', 'TEST_GPLX005', 'D', '2030-08-21', 'available', '2026-10-21 05:14:37', '2026-10-21 05:14:37');

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

--
-- Đang đổ dữ liệu cho bảng `driver_status_corrections`
--

INSERT INTO `driver_status_corrections` (`correction_id`, `driver_id`, `old_status`, `new_status`, `reason`, `changed_by`, `changed_at`) VALUES
(5, 7, 'available', 'inactive', 'Nhân viên 01 xử lý thay đổi: hệ thống tài xế 03 lỗi', 16, '2026-10-20 20:19:32'),
(6, 7, 'inactive', 'available', 'Nhân viên 01 xử lý thay đổi: hệ thống tài xế 03 lỗi', 16, '2026-10-20 20:22:48'),
(7, 7, 'available', 'inactive', 'Nhân viên thay đổi hộ tài xế 03', 16, '2026-10-21 12:12:24'),
(8, 7, 'inactive', 'available', 'Nhân viên thay đổi hộ tài xế 03', 16, '2026-10-21 12:12:52');

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `orders`
--

CREATE TABLE `orders` (
  `id` int(11) NOT NULL,
  `order_code` varchar(50) NOT NULL,
  `customer_id` int(11) NOT NULL,
  `sender_name` varchar(100) DEFAULT NULL,
  `sender_phone` varchar(20) DEFAULT NULL,
  `trip_id` int(11) DEFAULT NULL,
  `pickup_address` varchar(255) NOT NULL,
  `receiver_name` varchar(100) DEFAULT NULL,
  `receiver_phone` varchar(20) DEFAULT NULL,
  `delivery_address` varchar(255) NOT NULL,
  `goods_type` varchar(255) DEFAULT NULL,
  `total_weight` decimal(10,2) DEFAULT 0.00,
  `shipping_fee` decimal(12,2) DEFAULT 0.00,
  `payment_method` varchar(50) DEFAULT 'SENDER_PAY',
  `payment_status` enum('UNPAID','PAID') DEFAULT 'UNPAID',
  `status` enum('PENDING','CONFIRMED','PICKING_UP','IN_TRANSIT','DELIVERED','CANCELLED') DEFAULT 'PENDING',
  `note` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Đang đổ dữ liệu cho bảng `orders`
--

INSERT INTO `orders` (`id`, `order_code`, `customer_id`, `sender_name`, `sender_phone`, `trip_id`, `pickup_address`, `receiver_name`, `receiver_phone`, `delivery_address`, `goods_type`, `total_weight`, `shipping_fee`, `payment_method`, `payment_status`, `status`, `note`, `created_at`) VALUES
(14, 'DH2026102014105716', 1, 'Nguyễn Bảo Minh', '0324569853', 10, 'Thanh Xuân - Hà Nội', 'Phạm Công Định', '0235642563', 'Xuân Châu - Nam Định', 'Tượng đá cảnh', 30.00, 165000.00, 'RECEIVER_PAY', 'PAID', 'DELIVERED', 'Hàng dễ hư hỏng', '2026-10-20 12:10:57'),
(15, 'DH2026102015351377', 1, 'Nguyễn Bảo Minh', '0235668555', 11, 'Thanh Hóa', 'Nguyễn Tuấn Đạt', '0222556652', 'Hà Nội', 'Quần áo', 30.00, 165000.00, 'RECEIVER_PAY', 'PAID', 'DELIVERED', '', '2026-10-20 13:35:13'),
(16, 'DH2026102015370059', 1, 'Nguyễn Bảo Minh', '0235668555', 11, 'Thanh Hóa', 'Nguyễn Công Định', '0222556652', 'Hà Nội', 'Đồ điện', 50.00, 265000.00, 'RECEIVER_PAY', 'PAID', 'DELIVERED', 'Hàng dễ vỡ', '2026-10-20 13:37:00'),
(17, 'DH2026102021241566', 2, 'Trần Thị Mai', '0235698426', 11, 'Thanh Hóa', 'Nguyễn Tuấn Đạt', '0235658456', 'Hà Nội', 'Quần áo', 20.00, 115000.00, 'RECEIVER_PAY', 'PAID', 'DELIVERED', '', '2026-10-20 19:24:15'),
(22, 'DH2026102107061561', 2, 'Tran Thi Mai', '0233654895', 15, 'Hà Nội', 'Phạm Công Linh', '0236545652', 'Quảng Trị', 'Đồ điện', 20.00, 115000.00, 'SENDER_PAY', 'PAID', 'CONFIRMED', 'Hàng dễ vỡ', '2026-10-21 05:06:15'),
(23, 'DH2026102107065219', 2, 'Tran Thi Mai', '0222222564', 15, 'Hà Nội', 'Phạm Nhật Đạt', '0236545644', 'Quảng Trị', 'Đồ điện', 30.00, 165000.00, 'SENDER_PAY', 'PAID', 'CONFIRMED', 'Hàng dễ vỡ', '2026-10-21 05:06:52'),
(24, 'DH2026102107075031', 1, 'Nguyễn Văn Test', '0254658236', 14, 'Nam Định', 'Phạm Linh Đạt', '0256988456', 'Hài Phòng', 'Tủ lạnh', 30.00, 165000.00, 'RECEIVER_PAY', 'UNPAID', 'CONFIRMED', 'Hàng dễ vỡ', '2026-10-21 05:07:50'),
(25, 'DH2026102107320095', 2, 'Tran Thi Mai', '0233654895', 16, 'Hà Nội', 'Phạm Công Linh', '0236545644', 'Quảng Trị', 'Tủ lạnh', 50.00, 265000.00, 'RECEIVER_PAY', 'UNPAID', 'DELIVERED', 'Hàng dễ vỡ', '2026-10-21 05:32:00'),
(26, 'DH2026102107472972', 2, 'Tran Thi Mai', '0222222564', NULL, 'Hà Nội', 'Nguyễn Văn Tháng', '0256988456', 'Hài Phòng', 'Tủ lạnh', 50.00, 265000.00, 'SENDER_PAY', 'PAID', 'CANCELLED', 'Hàng dễ vỡ', '2026-10-21 05:47:29'),
(27, 'DH2026102107495448', 2, 'Tran Thi Mai', '0233654895', NULL, 'Hà Nội', 'Nguyễn Văn Tháng', '0236545644', 'Hài Phòng', 'Quần áo', 20.00, 115000.00, 'SENDER_PAY', 'PAID', 'CANCELLED', '', '2026-10-21 05:49:54'),
(28, 'DH2026102107504620', 2, 'Tran Thi Mai', '0222222564', NULL, 'Hà Nội', 'Nguyễn Văn Tháng', '0256988456', 'Quảng Trị', 'Đồ điện', 30.00, 165000.00, 'SENDER_PAY', 'PAID', 'CANCELLED', '', '2026-10-21 05:50:46');

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
-- Cấu trúc bảng cho bảng `support_messages`
--

CREATE TABLE `support_messages` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `ticket_id` bigint(20) UNSIGNED NOT NULL,
  `sender_user_id` bigint(20) UNSIGNED NOT NULL,
  `message` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Đang đổ dữ liệu cho bảng `support_messages`
--

INSERT INTO `support_messages` (`id`, `ticket_id`, `sender_user_id`, `message`, `created_at`) VALUES
(3, 2, 2, 'Cần chú ý thời gian của khách hàng', '2026-10-21 05:42:10'),
(4, 2, 16, 'Cảm ơn đã phản hồi!', '2026-10-21 05:43:12'),
(5, 3, 2, 'Cảm ơn shop', '2026-10-21 05:44:21'),
(6, 3, 16, 'Cảm ơn đã tin tưởng!', '2026-10-21 05:44:52');

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `support_tickets`
--

CREATE TABLE `support_tickets` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `ticket_code` varchar(30) NOT NULL,
  `customer_id` bigint(20) UNSIGNED NOT NULL,
  `order_id` bigint(20) UNSIGNED DEFAULT NULL,
  `category` enum('CONSULTATION','COMPLAINT','INCIDENT','FEEDBACK','OTHER') NOT NULL DEFAULT 'OTHER',
  `subject` varchar(200) NOT NULL,
  `description` text NOT NULL,
  `status` enum('PENDING','PROCESSING','RESOLVED','CLOSED') NOT NULL DEFAULT 'PENDING',
  `assigned_to` bigint(20) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Đang đổ dữ liệu cho bảng `support_tickets`
--

INSERT INTO `support_tickets` (`id`, `ticket_code`, `customer_id`, `order_id`, `category`, `subject`, `description`, `status`, `assigned_to`, `created_at`, `updated_at`) VALUES
(2, 'HT20261021074210889', 2, NULL, 'FEEDBACK', 'Đơn hàng giao chậm', 'Cần chú ý thời gian của khách hàng', 'RESOLVED', 16, '2026-10-21 05:42:10', '2026-10-21 05:43:37'),
(3, 'HT20261021074421128', 2, NULL, 'OTHER', 'Cảm ơn shop', 'Cảm ơn shop', 'CLOSED', 16, '2026-10-21 05:44:21', '2026-10-21 05:45:01');

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `trips`
--

CREATE TABLE `trips` (
  `id` int(11) NOT NULL,
  `driver_id` bigint(20) UNSIGNED DEFAULT NULL,
  `vehicle_id` bigint(20) UNSIGNED DEFAULT NULL,
  `start_location` varchar(255) NOT NULL,
  `end_location` varchar(255) NOT NULL,
  `start_time` datetime DEFAULT NULL,
  `end_time` datetime DEFAULT NULL,
  `status` enum('PLANNED','IN_PROGRESS','COMPLETED','CANCELLED') DEFAULT 'PLANNED',
  `goods_name` varchar(255) DEFAULT NULL,
  `weight` decimal(10,2) DEFAULT NULL,
  `goods_value` decimal(15,2) DEFAULT NULL,
  `receiver_name` varchar(255) DEFAULT NULL,
  `receiver_phone` varchar(20) DEFAULT NULL,
  `fuel_cost` decimal(15,2) DEFAULT 0.00 COMMENT 'Tiền xăng dầu',
  `toll_fee` decimal(15,2) DEFAULT 0.00 COMMENT 'Phí cầu đường',
  `other_cost` decimal(15,2) DEFAULT 0.00 COMMENT 'Chi phí khác (luật lá, bốc xếp)',
  `note` text DEFAULT NULL,
  `actual_started_at` datetime DEFAULT NULL COMMENT 'Thời điểm tài xế thực tế bắt đầu di chuyển'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Đang đổ dữ liệu cho bảng `trips`
--

INSERT INTO `trips` (`id`, `driver_id`, `vehicle_id`, `start_location`, `end_location`, `start_time`, `end_time`, `status`, `goods_name`, `weight`, `goods_value`, `receiver_name`, `receiver_phone`, `fuel_cost`, `toll_fee`, `other_cost`, `note`, `actual_started_at`) VALUES
(10, 8, 11, 'Thành phố Hà Nội', 'Tỉnh Ninh Bình', '2026-10-20 17:29:00', '2026-10-20 20:26:50', 'COMPLETED', NULL, NULL, NULL, NULL, NULL, 0.00, 0.00, 0.00, NULL, '2026-10-20 20:10:35'),
(11, 7, 10, 'Thanh Hóa', 'Hà Nội', '2026-10-20 20:39:00', '2026-10-21 03:17:33', 'COMPLETED', NULL, NULL, NULL, NULL, NULL, 0.00, 0.00, 0.00, NULL, '2026-10-21 03:17:02'),
(14, 8, 10, 'Nam Định', 'Hài Phòng', '2026-10-21 12:08:00', '2026-10-21 12:31:01', 'COMPLETED', NULL, NULL, NULL, NULL, NULL, 0.00, 0.00, 0.00, NULL, '2026-10-21 12:30:30'),
(15, 7, 11, 'Hà Nội', 'Quảng Trị', '2026-10-21 12:08:00', '2026-10-21 12:26:19', 'COMPLETED', NULL, NULL, NULL, NULL, NULL, 0.00, 0.00, 0.00, NULL, '2026-10-21 12:24:54'),
(16, 7, 11, 'Hà Nội', 'Quảng Trị', '2026-10-21 12:32:00', '2026-10-21 12:38:26', 'COMPLETED', NULL, NULL, NULL, NULL, NULL, 0.00, 0.00, 0.00, NULL, '2026-10-21 12:37:52');

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

--
-- Đang đổ dữ liệu cho bảng `users`
--

INSERT INTO `users` (`id`, `username`, `password`, `full_name`, `role`, `status`, `created_at`) VALUES
(1, 'customer01', '$2y$12$nwS10N7S4P0jf1aRk4eI5.OH42s99diDmDhqUc07G2elUMcVTLtFm', 'Nguyen Van Test', 'CUSTOMER', 1, '2026-10-06 16:15:42'),
(2, 'customer02', '$2y$10$/nrf.7TtISzH7peAP7IeaO3tbOg1o2ZTtgqp3lzHNi8r9VwaZHb9S', 'Tran Thi Mai', 'CUSTOMER', 1, '2026-10-07 05:38:03'),
(3, 'customer03', '$2y$10$b8B/gCXcdr9NlKoGZib.nuI1RtH/SSIYqSdWigpREiTYXHQ35O3uO', 'Le Quang Huy', 'CUSTOMER', 1, '2026-10-07 05:38:03'),
(4, 'customer04', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llCj8V6M4xVqV7q9rWqG', 'Pham Ngoc Anh', 'CUSTOMER', 1, '2026-10-07 05:38:03'),
(16, 'staff01', '$2y$10$NqnKqTAvmrgA4uZlEpgSA.vqmWKfVknCzABMXXtkBL/xPa2fFsgxa', 'Nhân viên 01', 'STAFF', 1, '2026-10-07 07:58:21'),
(17, 'staff02', '$2y$10$NqnKqTAvmrgA4uZlEpgSA.vqmWKfVknCzABMXXtkBL/xPa2fFsgxa', 'Nhân viên 02', 'STAFF', 1, '2026-10-07 07:58:21'),
(18, 'staff03', '$2y$10$NqnKqTAvmrgA4uZlEpgSA.vqmWKfVknCzABMXXtkBL/xPa2fFsgxa', 'Nhân viên 03', 'STAFF', 1, '2026-10-07 07:58:21'),
(19, 'staff04', '$2y$10$NqnKqTAvmrgA4uZlEpgSA.vqmWKfVknCzABMXXtkBL/xPa2fFsgxa', 'Nhân viên 04', 'STAFF', 1, '2026-10-07 07:58:21'),
(20, 'staff05', '$2y$10$NqnKqTAvmrgA4uZlEpgSA.vqmWKfVknCzABMXXtkBL/xPa2fFsgxa', 'Nhân viên 05', 'STAFF', 1, '2026-10-07 07:58:21'),
(23, 'driver03', '$2y$10$NqnKqTAvmrgA4uZlEpgSA.vqmWKfVknCzABMXXtkBL/xPa2fFsgxa', 'Tài xế 03', 'DRIVER', 1, '2026-10-07 07:58:21'),
(24, 'driver04', '$2y$10$NqnKqTAvmrgA4uZlEpgSA.vqmWKfVknCzABMXXtkBL/xPa2fFsgxa', 'Tài xế 04', 'DRIVER', 1, '2026-10-07 07:58:21'),
(33, 'admin01', '$2y$10$0/gauViu4Xx4x9vGndA32OFhbIQ2IX5igzZ1NXvmDwjt20hsycwta', 'Quản trị viên 01', 'ADMIN', 1, '2026-10-07 13:44:17'),
(34, 'admin02', '$2y$10$NqnKqTAvmrgA4uZlEpgSA.vqmWKfVknCzABMXXtkBL/xPa2fFsgxa', 'Quản trị viên 02', 'ADMIN', 1, '2026-10-07 13:44:17'),
(38, 'driver05', '$2y$10$YpQvoC9jQaQfuQobmzfBXe3EnCGMDo4geWKKnluvGgAdHRSZsiAUK', 'Tài xế 05', 'DRIVER', 1, '2026-10-21 05:14:37');

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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Đang đổ dữ liệu cho bảng `vehicles`
--

INSERT INTO `vehicles` (`vehicle_id`, `license_plate`, `vehicle_type`, `brand`, `capacity`, `status`, `created_at`, `updated_at`) VALUES
(10, '51C-222.22', 'Xe tải trung', 'Isuzu', 100.00, 'available', '2026-10-20 12:42:58', '2026-10-21 05:31:01'),
(11, '51C-333.33', 'Xe tải', 'Isuzu', 50.00, 'available', '2026-10-20 12:43:22', '2026-10-21 05:38:26');

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
-- Chỉ mục cho bảng `support_messages`
--
ALTER TABLE `support_messages`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_message_ticket` (`ticket_id`,`created_at`);

--
-- Chỉ mục cho bảng `support_tickets`
--
ALTER TABLE `support_tickets`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `ticket_code` (`ticket_code`),
  ADD KEY `idx_ticket_customer` (`customer_id`,`created_at`),
  ADD KEY `idx_ticket_status` (`status`,`created_at`),
  ADD KEY `idx_ticket_assigned` (`assigned_to`);

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
  MODIFY `assignment_id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'Khóa chính của bản ghi phân công', AUTO_INCREMENT=37;

--
-- AUTO_INCREMENT cho bảng `assignment_requests`
--
ALTER TABLE `assignment_requests`
  MODIFY `request_id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT cho bảng `customers`
--
ALTER TABLE `customers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT cho bảng `drivers`
--
ALTER TABLE `drivers`
  MODIFY `driver_id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'Khóa chính nội bộ của tài xế', AUTO_INCREMENT=20;

--
-- AUTO_INCREMENT cho bảng `driver_status_corrections`
--
ALTER TABLE `driver_status_corrections`
  MODIFY `correction_id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT cho bảng `orders`
--
ALTER TABLE `orders`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=29;

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
-- AUTO_INCREMENT cho bảng `support_messages`
--
ALTER TABLE `support_messages`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT cho bảng `support_tickets`
--
ALTER TABLE `support_tickets`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT cho bảng `trips`
--
ALTER TABLE `trips`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT cho bảng `trip_orders`
--
ALTER TABLE `trip_orders`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT cho bảng `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=39;

--
-- AUTO_INCREMENT cho bảng `vehicles`
--
ALTER TABLE `vehicles`
  MODIFY `vehicle_id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'Khóa chính nội bộ của phương tiện', AUTO_INCREMENT=13;

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
-- Các ràng buộc cho bảng `customers`
--
ALTER TABLE `customers`
  ADD CONSTRAINT `fk_customers_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

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
-- Các ràng buộc cho bảng `support_messages`
--
ALTER TABLE `support_messages`
  ADD CONSTRAINT `fk_support_message_ticket` FOREIGN KEY (`ticket_id`) REFERENCES `support_tickets` (`id`) ON DELETE CASCADE;

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
