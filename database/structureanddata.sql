-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Máy chủ: 127.0.0.1
-- Thời gian đã tạo: Th10 13, 2026 lúc 07:58 PM
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

--
-- Đang đổ dữ liệu cho bảng `assignments`
--

INSERT INTO `assignments` (`assignment_id`, `trip_id`, `driver_id`, `vehicle_id`, `assigned_by`, `assigned_at`, `completed_at`, `cancelled_at`, `notes`, `status`, `created_at`, `updated_at`, `accepted_at`) VALUES
(1, 2, 5, 9, 17, '2026-10-07 19:24:55', NULL, '2026-10-07 19:25:26', 'Tài xế 01', 'cancelled', '2026-10-07 12:24:55', '2026-10-07 12:25:26', NULL),
(2, 1, 6, 6, 17, '2026-10-07 19:30:04', NULL, '2026-10-07 19:30:20', 'Tài xế 02', 'cancelled', '2026-10-07 12:30:04', '2026-10-07 12:30:20', NULL),
(3, 2, 7, 9, 17, '2026-10-07 19:30:20', NULL, '2026-10-07 19:30:52', 'Tài xế 02', 'cancelled', '2026-10-07 12:30:20', '2026-10-07 12:30:52', NULL),
(4, 2, 5, 9, 17, '2026-10-07 19:30:52', '2026-10-07 19:31:39', NULL, 'Tài xế 02', 'completed', '2026-10-07 12:30:52', '2026-10-07 12:31:39', NULL),
(5, 2, 5, 7, 16, '2026-10-08 12:07:14', NULL, '2026-10-08 12:10:50', NULL, 'cancelled', '2026-10-08 05:07:14', '2026-10-08 05:10:50', NULL),
(6, 2, 5, 9, 16, '2026-10-08 12:10:50', '2026-10-08 12:11:22', NULL, NULL, 'completed', '2026-10-08 05:10:50', '2026-10-08 05:11:22', NULL),
(7, 2, 5, 7, 16, '2026-10-12 15:13:32', NULL, '2026-10-13 01:30:22', NULL, 'cancelled', '2026-10-12 08:13:32', '2026-10-12 18:30:22', NULL),
(8, 2, 6, 9, 16, '2026-10-13 01:30:22', '2026-10-13 02:14:00', NULL, NULL, 'completed', '2026-10-12 18:30:22', '2026-10-12 19:14:00', '2026-10-13 01:34:38'),
(9, 1, 7, 4, 16, '2026-10-13 01:40:25', NULL, '2026-10-13 02:15:42', '.', 'cancelled', '2026-10-12 18:40:25', '2026-10-12 19:15:42', NULL),
(10, 1, 17, 7, 16, '2026-10-13 02:18:02', NULL, '2026-10-13 02:23:20', NULL, 'cancelled', '2026-10-12 19:18:02', '2026-10-12 19:23:20', NULL),
(11, 1, 19, 7, 16, '2026-10-13 02:34:40', '2026-10-13 02:38:11', NULL, '.', 'completed', '2026-10-12 19:34:40', '2026-10-12 19:38:11', '2026-10-13 02:35:57'),
(12, 100, 20, 6, 17, '2026-10-13 16:16:39', NULL, '2026-10-13 16:17:10', 'Tài xế 07', 'cancelled', '2026-10-13 09:16:39', '2026-10-13 09:17:10', NULL),
(13, 100, 8, 1, 17, '2026-10-13 16:17:10', NULL, '2026-10-13 16:20:17', 'Tài xế 04\n[Hủy bởi tài khoản #17] Tài xế báo hủy', 'cancelled', '2026-10-13 09:17:10', '2026-10-13 09:20:17', NULL),
(14, 100, 8, 1, 17, '2026-10-13 16:21:00', NULL, '2026-10-13 16:27:17', NULL, 'cancelled', '2026-10-13 09:21:00', '2026-10-13 09:27:17', '2026-10-13 16:22:36'),
(15, 100, 7, 8, 16, '2026-10-13 16:27:17', NULL, '2026-10-13 16:28:18', '\n[Hủy bởi tài khoản #16] Tài xế 03 báo hủy', 'cancelled', '2026-10-13 09:27:17', '2026-10-13 09:28:18', '2026-10-13 16:27:55'),
(16, 100, 6, 9, 16, '2026-10-13 16:29:59', '2026-10-13 16:35:15', NULL, 'Tài xế 02 thực hiện chuyến', 'completed', '2026-10-13 09:29:59', '2026-10-13 09:35:15', '2026-10-13 16:33:12'),
(17, 101, 6, 7, 16, '2026-10-13 17:46:20', NULL, '2026-10-13 17:46:28', 'Tài xế 02 thực hiện chuyến', 'cancelled', '2026-10-13 10:46:20', '2026-10-13 10:46:28', NULL),
(18, 101, 7, 9, 16, '2026-10-13 17:46:28', NULL, '2026-10-13 17:47:12', 'Tài xế 02 thực hiện chuyến\n[Hủy bởi tài khoản #16] Hệ thống tài xế báo lỗi không thể hủy. Nhân viên tiếp nhận hủy', 'cancelled', '2026-10-13 10:46:28', '2026-10-13 10:47:12', NULL),
(19, 101, 8, 3, 16, '2026-10-13 17:47:42', '2026-10-13 17:51:23', NULL, 'Tài xế 04 thực hiện chuyến', 'completed', '2026-10-13 10:47:42', '2026-10-13 10:51:23', '2026-10-13 17:49:24'),
(20, 100, 6, 7, 16, '2026-10-13 17:53:19', NULL, '2026-10-13 17:58:00', 'Tài xế 02 thực hiện chuyến', 'cancelled', '2026-10-13 10:53:19', '2026-10-13 10:58:00', NULL),
(21, 100, 7, 7, 16, '2026-10-13 18:02:12', NULL, '2026-10-13 18:09:17', '\n[Hủy bởi tài khoản #16] .', 'cancelled', '2026-10-13 11:02:12', '2026-10-13 11:09:17', NULL),
(22, 100, 6, 7, 16, '2026-10-13 19:12:40', NULL, '2026-10-13 19:13:35', 'Tài xế 02 thực hiện chuyến', 'cancelled', '2026-10-13 12:12:40', '2026-10-13 12:13:35', NULL),
(23, 100, 19, 9, 16, '2026-10-13 19:13:35', NULL, '2026-10-13 19:16:25', 'Tài xế 05 thực hiện chuyến\n[Hủy bởi tài khoản #16] Tài xế gặp vấn đề', 'cancelled', '2026-10-13 12:13:35', '2026-10-13 12:16:25', '2026-10-13 19:14:15'),
(24, 100, 19, 9, 16, '2026-10-13 19:18:51', '2026-10-13 19:20:33', NULL, 'tx05 thực hiện chuyến', 'completed', '2026-10-13 12:18:51', '2026-10-13 12:20:33', '2026-10-13 19:19:48'),
(25, 104, 6, 7, 16, '2026-10-13 22:13:18', '2026-10-13 22:22:52', NULL, 'Tx02 thc', 'completed', '2026-10-13 15:13:18', '2026-10-13 15:22:52', '2026-10-13 22:19:28'),
(26, 103, 7, 9, 16, '2026-10-13 22:23:47', NULL, '2026-10-13 22:30:37', 'tx03 thc', 'cancelled', '2026-10-13 15:23:47', '2026-10-13 15:30:37', NULL),
(27, 103, 8, 4, 16, '2026-10-13 22:31:13', NULL, '2026-10-13 22:33:36', 'tx04 thc', 'cancelled', '2026-10-13 15:31:13', '2026-10-13 15:33:36', '2026-10-13 22:32:00'),
(28, 103, 5, 5, 16, '2026-10-13 23:10:00', NULL, '2026-10-13 23:18:49', 'tx01 thc', 'cancelled', '2026-10-13 16:10:00', '2026-10-13 16:18:49', '2026-10-13 23:10:31'),
(29, 102, 6, 9, 16, '2026-10-13 23:13:32', NULL, '2026-10-13 23:55:20', 'tx02 thc', 'cancelled', '2026-10-13 16:13:32', '2026-10-13 16:55:20', '2026-10-13 23:14:21'),
(30, 105, 22, 6, 16, '2026-10-14 00:06:43', NULL, '2026-10-14 00:07:50', 'tx08 thc', 'cancelled', '2026-10-13 17:06:43', '2026-10-13 17:07:50', '2026-10-14 00:07:03'),
(31, 105, 22, 6, 16, '2026-10-14 00:08:32', NULL, '2026-10-14 00:09:06', '.', 'cancelled', '2026-10-13 17:08:32', '2026-10-13 17:09:06', '2026-10-14 00:08:47'),
(32, 107, 22, 1, 16, '2026-10-14 00:20:20', NULL, '2026-10-14 00:20:36', '.', 'cancelled', '2026-10-13 17:20:20', '2026-10-13 17:20:36', NULL),
(33, 107, 22, 1, 16, '2026-10-14 00:20:36', NULL, '2026-10-14 00:20:59', '.', 'cancelled', '2026-10-13 17:20:36', '2026-10-13 17:20:59', NULL),
(34, 107, 22, 1, 16, '2026-10-14 00:20:59', NULL, '2026-10-14 00:22:05', NULL, 'cancelled', '2026-10-13 17:20:59', '2026-10-13 17:22:05', NULL),
(35, 107, 22, 1, 16, '2026-10-14 00:22:23', NULL, '2026-10-14 00:22:57', '.', 'cancelled', '2026-10-13 17:22:23', '2026-10-13 17:22:57', '2026-10-14 00:22:38'),
(36, 107, 20, 1, 16, '2026-10-14 00:23:37', NULL, '2026-10-14 00:25:01', '3', 'cancelled', '2026-10-13 17:23:37', '2026-10-13 17:25:01', '2026-10-14 00:24:30'),
(37, 107, 22, 1, 17, '2026-10-14 00:25:13', NULL, '2026-10-14 00:27:02', '4', 'cancelled', '2026-10-13 17:25:13', '2026-10-13 17:27:02', '2026-10-14 00:25:45'),
(38, 107, 22, 1, 16, '2026-10-14 00:27:24', NULL, '2026-10-14 00:27:59', '5', 'cancelled', '2026-10-13 17:27:24', '2026-10-13 17:27:59', '2026-10-14 00:27:40'),
(39, 106, 22, 1, 16, '2026-10-14 00:28:33', NULL, '2026-10-14 00:33:37', NULL, 'cancelled', '2026-10-13 17:28:33', '2026-10-13 17:33:37', '2026-10-14 00:29:53'),
(40, 106, 5, 7, 16, '2026-10-14 00:38:26', NULL, '2026-10-14 00:51:29', 'tx01', 'cancelled', '2026-10-13 17:38:26', '2026-10-13 17:51:29', '2026-10-14 00:50:12'),
(41, 106, 5, 9, 16, '2026-10-14 00:51:47', NULL, '2026-10-14 00:53:46', 'tx01\n[Hủy bởi tài khoản #16] Lỗi', 'cancelled', '2026-10-13 17:51:47', '2026-10-13 17:53:46', NULL),
(42, 106, 5, 9, 16, '2026-10-14 00:54:06', NULL, '2026-10-14 00:54:29', '\n[Hủy bởi tài khoản #16] lỗi 2', 'cancelled', '2026-10-13 17:54:06', '2026-10-13 17:54:29', NULL);

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
(1, 19, 8, 'decline_assignment', 'Tài xế ốm, cần thay đổi tài xế', 'rejected', '2026-10-13 17:49:18', 16, '2026-10-13 17:57:07', 'Lỗi'),
(2, 20, 6, 'decline_assignment', 'Tài xế 04 bị ốm', 'approved', '2026-10-13 17:54:20', 16, '2026-10-13 17:58:00', 'Tài xế 04 yêu cầu hủy chuyến'),
(3, 21, 7, 'decline_assignment', '.', 'rejected', '2026-10-13 18:04:02', 16, '2026-10-13 19:17:37', '.'),
(4, 24, 19, 'cancel_trip', '.', 'rejected', '2026-10-13 19:20:27', 16, '2026-10-13 19:21:06', '.'),
(5, 25, 6, 'decline_assignment', 'Tx02 gặp vấn đề', 'rejected', '2026-10-13 22:17:24', 17, '2026-10-13 22:18:51', 'Vẫn tiếp tục thực hiện chuyến'),
(6, 25, 6, 'cancel_assignment', 'tx02 gặp vấn đề (đã tiếp nhận)', 'rejected', '2026-10-13 22:19:55', 16, '2026-10-13 22:20:40', 'Vấn tiếp tục chuyến'),
(7, 25, 6, 'cancel_trip', 'tx02 gặp vấn đề (đang thực hiện chuyến)', 'rejected', '2026-10-13 22:21:30', 16, '2026-10-13 22:22:33', 'Vẫn tiếp tục chuyến'),
(8, 26, 7, 'decline_assignment', 'tx03 gặp vấn đề', 'approved', '2026-10-13 22:24:20', 16, '2026-10-13 22:30:37', 'tx03 gặp vấn đề, chấp nhận xử lý'),
(9, 27, 8, 'cancel_assignment', 'tx04 gặp vấn đề', 'approved', '2026-10-13 22:32:15', 16, '2026-10-13 22:33:36', 'chấp nhận xử lý'),
(10, 28, 5, 'cancel_trip', 'tx01 gặp vấn đề', 'approved', '2026-10-13 23:17:45', 16, '2026-10-13 23:18:49', 'chấp nhận yêu cầu'),
(11, 29, 6, 'cancel_trip', 'tx02 gặp vấn đề', 'approved', '2026-10-13 23:54:33', 16, '2026-10-13 23:55:20', 'chấp nhận yêu cầu xử lý'),
(12, 30, 22, 'cancel_assignment', '.', 'approved', '2026-10-14 00:07:11', 16, '2026-10-14 00:07:50', '.'),
(13, 31, 22, 'cancel_trip', '.', 'approved', '2026-10-14 00:08:52', 16, '2026-10-14 00:09:06', '.'),
(14, 34, 22, 'decline_assignment', '1', 'approved', '2026-10-14 00:21:38', 16, '2026-10-14 00:22:05', '1'),
(15, 35, 22, 'cancel_assignment', '2', 'approved', '2026-10-14 00:22:42', 16, '2026-10-14 00:22:57', '2'),
(16, 36, 20, 'cancel_trip', '3', 'approved', '2026-10-14 00:24:37', 17, '2026-10-14 00:25:01', '3'),
(17, 37, 22, 'cancel_trip', '4', 'rejected', '2026-10-14 00:25:49', 16, '2026-10-14 00:26:19', '4'),
(18, 37, 22, 'cancel_trip', '4', 'approved', '2026-10-14 00:26:43', 16, '2026-10-14 00:27:02', '4'),
(19, 38, 22, 'cancel_assignment', '5', 'approved', '2026-10-14 00:27:44', 16, '2026-10-14 00:27:59', '5'),
(20, 39, 22, 'decline_assignment', '5', 'rejected', '2026-10-14 00:29:12', 16, '2026-10-14 00:29:33', '5'),
(21, 39, 22, 'cancel_trip', '6', 'approved', '2026-10-14 00:29:58', 16, '2026-10-14 00:33:37', '6'),
(22, 40, 5, 'cancel_assignment', 'hủy', 'rejected', '2026-10-14 00:50:19', 16, '2026-10-14 00:50:48', '.'),
(23, 40, 5, 'cancel_trip', '.', 'approved', '2026-10-14 00:51:08', 16, '2026-10-14 00:51:29', '.');

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
(4, 4, 'Pham Ngoc Anh', '0938123456', 'anh.pham@gmail.com', 'Ho Chi Minh', '2026-10-07 05:38:04'),
(5, 35, 'Nguyen Bao Minh', '0987654323', 'nbm@gmail.com', 'Hà Nội', '2026-10-07 14:18:50'),
(6, NULL, 'Khách hàng 08', '06463637564', 'customer08@gmail.com', 'Hài Phòng', '2026-10-08 07:33:43');

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

--
-- Đang đổ dữ liệu cho bảng `drivers`
--

INSERT INTO `drivers` (`driver_id`, `user_id`, `driver_code`, `full_name`, `phone`, `address`, `license_number`, `license_class`, `license_expiry`, `status`, `created_at`, `updated_at`) VALUES
(5, 21, 'TEST_TX001', 'Tài xế 01', '0901000001', 'Hà Nội', 'TEST_GPLX001', 'C', '2030-12-31', 'available', '2026-10-07 07:58:22', '2026-10-13 17:51:29'),
(6, 22, 'TEST_TX002', 'Tài xế 02', '0901000002', 'Hà Nội', 'TEST_GPLX002', 'C', '2030-12-31', 'available', '2026-10-07 07:58:22', '2026-10-13 16:55:20'),
(7, 23, 'TEST_TX003', 'Tài xế 03', '0901000003', 'Hà Nội', 'TEST_GPLX003', 'C', '2030-12-31', 'available', '2026-10-07 07:58:22', '2026-10-07 12:30:53'),
(8, 24, 'TEST_TX004', 'Tài xế 04', '0901000004', 'Hà Nội', 'TEST_GPLX004', 'C', '2030-12-31', 'available', '2026-10-07 07:58:22', '2026-10-13 10:51:23'),
(17, 31, 'TEST_TX006', 'Nguyễn Nhật Linh', '0123456789', 'Nam Định', 'TEST_GPLX006', 'D', '2030-02-01', 'inactive', '2026-10-07 09:21:29', '2026-10-12 19:24:03'),
(19, 39, 'TEST_TX005', 'Tài xế 05', '0965784561', 'Hà Nội', 'TEST_GPLX005', 'D', '2030-05-09', 'available', '2026-10-12 19:28:22', '2026-10-13 12:20:33'),
(20, 40, 'TEST_TX007', 'Tài xế 07', '0654789541', 'Hải Phòng', 'TEST_GPLX007', 'B', '2030-02-08', 'available', '2026-10-12 19:54:00', '2026-10-13 17:25:01'),
(22, 42, 'TEST_TX00̉́́́8', 'Tài xế 08', '0236548956', 'Nam Định', 'TEST_GPLX008', 'D', '2030-05-09', 'available', '2026-10-13 11:00:29', '2026-10-13 17:33:37');

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
(1, 20, 'available', 'inactive', 'Tài xế 07 lỗi thay đổi trạng thái', 16, '2026-10-13 17:37:32'),
(2, 5, 'inactive', 'available', 'hệ thống tx lỗi, tx đã ss', 16, '2026-10-13 22:14:16');

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

--
-- Đang đổ dữ liệu cho bảng `orders`
--

INSERT INTO `orders` (`id`, `order_code`, `customer_id`, `pickup_address`, `delivery_address`, `total_weight`, `shipping_fee`, `payment_status`, `status`, `created_at`) VALUES
(2, 'DH002', 2, 'Hai Phong', 'Ha Noi', 2.50, 45000.00, 'PAID', 'PENDING', '2026-10-07 05:38:04'),
(4, 'DH004', 4, 'Ho Chi Minh', 'Binh Duong', 1.20, 30000.00, 'UNPAID', 'PENDING', '2026-10-07 05:38:04'),
(5, 'DH005', 2, 'Ha Noi', 'Ninh Binh', 12.50, 120000.00, 'PAID', 'DELIVERED', '2026-10-07 05:38:04'),
(6, 'DH006', 3, 'Ho Chi Minh', 'Can Tho', 4.80, 70000.00, 'UNPAID', 'CANCELLED', '2026-10-07 05:38:04');

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
  `actual_started_at` datetime DEFAULT NULL COMMENT 'Thời điểm tài xế thực tế bắt đầu di chuyển',
  `end_time` datetime DEFAULT NULL,
  `status` enum('PLANNED','IN_PROGRESS','COMPLETED','CANCELLED') DEFAULT 'PLANNED'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Đang đổ dữ liệu cho bảng `trips`
--

INSERT INTO `trips` (`id`, `driver_id`, `vehicle_id`, `start_location`, `end_location`, `start_time`, `actual_started_at`, `end_time`, `status`) VALUES
(1, 19, 7, 'Hà Nội', 'Hải Phòng', '2026-10-08 08:00:00', NULL, '2026-10-13 02:38:11', 'COMPLETED'),
(2, 6, 9, 'Hà Nội', 'Ninh Bình', '2026-10-08 09:00:00', NULL, '2026-10-13 02:14:00', 'COMPLETED'),
(100, 19, 9, 'Kho Hà Nội', 'Kho Hải Phòng', '2026-10-14 17:45:34', NULL, '2026-10-13 19:20:33', 'COMPLETED'),
(101, 8, 3, 'Kho Bắc Ninh', 'Kho Đà Nẵng', '2026-10-15 17:45:34', NULL, '2026-10-13 17:51:23', 'COMPLETED'),
(102, 6, 9, 'Kho Hà Nội', 'Kho Hải Phòng', '2026-10-16 22:12:05', '2026-10-13 23:14:29', NULL, 'CANCELLED'),
(103, 5, 5, 'Kho Bắc Ninh', 'Kho Đà Nẵng', '2026-10-17 22:12:05', '2026-10-13 23:11:06', NULL, 'CANCELLED'),
(104, 6, 7, 'Kho Hải Phòng', 'Kho Hà Nội', '2026-10-18 22:12:05', NULL, '2026-10-13 22:22:52', 'COMPLETED'),
(105, 22, 6, 'Kho Hà Nội', 'Kho Bắc Ninh', '2026-10-15 00:06:04', '2026-10-14 00:08:48', NULL, 'CANCELLED'),
(106, 5, 9, 'Kho Hà Nội', 'Kho Bắc Ninh', '2026-10-15 00:17:33', '2026-10-14 00:29:54', NULL, 'PLANNED'),
(107, 22, 1, 'Kho Hà Nội', 'Kho Hải Phòng', '2026-10-16 00:17:33', '2026-10-14 00:24:32', NULL, 'CANCELLED');

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
(1, 'customer01', '$2y$10$/w2l9pvFcDwxJMvOvD7GCuX1hKM0YJIDuXSi02QrZ6PLBXnWU5od2', 'Nguyen Van Test', 'CUSTOMER', 1, '2026-10-06 16:15:42'),
(2, 'customer02', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llCj8V6M4xVqV7q9rWqG', 'Tran Thi Mai', 'CUSTOMER', 1, '2026-10-07 05:38:03'),
(3, 'customer03', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llCj8V6M4xVqV7q9rWqG', 'Le Quang Huy', 'CUSTOMER', 1, '2026-10-07 05:38:03'),
(4, 'customer04', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llCj8V6M4xVqV7q9rWqG', 'Pham Ngoc Anh', 'CUSTOMER', 1, '2026-10-07 05:38:03'),
(16, 'staff01', '$2y$10$NqnKqTAvmrgA4uZlEpgSA.vqmWKfVknCzABMXXtkBL/xPa2fFsgxa', 'Nhân viên 01', 'STAFF', 1, '2026-10-07 07:58:21'),
(17, 'staff02', '$2y$10$NqnKqTAvmrgA4uZlEpgSA.vqmWKfVknCzABMXXtkBL/xPa2fFsgxa', 'Nhân viên 02', 'STAFF', 1, '2026-10-07 07:58:21'),
(18, 'staff03', '$2y$10$NqnKqTAvmrgA4uZlEpgSA.vqmWKfVknCzABMXXtkBL/xPa2fFsgxa', 'Nhân viên 03', 'STAFF', 1, '2026-10-07 07:58:21'),
(19, 'staff04', '$2y$10$NqnKqTAvmrgA4uZlEpgSA.vqmWKfVknCzABMXXtkBL/xPa2fFsgxa', 'Nhân viên 04', 'STAFF', 1, '2026-10-07 07:58:21'),
(20, 'staff05', '$2y$10$NqnKqTAvmrgA4uZlEpgSA.vqmWKfVknCzABMXXtkBL/xPa2fFsgxa', 'Nhân viên 05', 'STAFF', 1, '2026-10-07 07:58:21'),
(21, 'driver01', '$2y$10$NqnKqTAvmrgA4uZlEpgSA.vqmWKfVknCzABMXXtkBL/xPa2fFsgxa', 'Tài xế 01', 'DRIVER', 1, '2026-10-07 07:58:21'),
(22, 'driver02', '$2y$10$NqnKqTAvmrgA4uZlEpgSA.vqmWKfVknCzABMXXtkBL/xPa2fFsgxa', 'Tài xế 02', 'DRIVER', 1, '2026-10-07 07:58:21'),
(23, 'driver03', '$2y$10$NqnKqTAvmrgA4uZlEpgSA.vqmWKfVknCzABMXXtkBL/xPa2fFsgxa', 'Tài xế 03', 'DRIVER', 1, '2026-10-07 07:58:21'),
(24, 'driver04', '$2y$10$NqnKqTAvmrgA4uZlEpgSA.vqmWKfVknCzABMXXtkBL/xPa2fFsgxa', 'Tài xế 04', 'DRIVER', 1, '2026-10-07 07:58:21'),
(33, 'admin01', '$2y$10$NqnKqTAvmrgA4uZlEpgSA.vqmWKfVknCzABMXXtkBL/xPa2fFsgxa', 'Quản trị viên 01', 'ADMIN', 1, '2026-10-07 13:44:17'),
(34, 'admin02', '$2y$10$NqnKqTAvmrgA4uZlEpgSA.vqmWKfVknCzABMXXtkBL/xPa2fFsgxa', 'Quản trị viên 02', 'ADMIN', 1, '2026-10-07 13:44:17'),
(35, 'customer09', '$2y$10$qyqhYNT7WQ6mIP/SSheuDeE9XGDX9nNfSH2Sfn84iochQPqDCmEVW', 'Nguyen Bao Minh', 'CUSTOMER', 0, '2026-10-07 14:18:50'),
(36, 'customer06', '$2y$10$qar.SOUvdBnz.gapum4KQucYOpY8N5s/LfTZtjWdhOm/Rg6UX21r2', 'Nguyễn Nhật Linh', 'CUSTOMER', 1, '2026-10-08 03:47:14'),
(39, 'driver05', '$2y$10$HgY/NAljc67bnVcuZk6mQeKMwng.FJdQcX6fccskE8bXJD8dTjB2e', 'Tài xế 05', 'DRIVER', 1, '2026-10-12 19:28:22'),
(40, 'driver07', '$2y$10$U.ZcoOQXTPpIq0wCGd1/y.HZAoAmPS0PAHQJKRdTg2ce/1wNaorY2', 'Tài xế 07', 'DRIVER', 1, '2026-10-12 19:54:00'),
(42, 'driver08', '$2y$10$vPQQfKwuHzN6dUq/lZ3QvOUTFqs9npIF.w5.vYmD6Si5jrKUIObj2', 'Tài xế 08', 'DRIVER', 1, '2026-10-13 11:00:29'),
(44, 'driver09', '$2y$10$yf2dT/dg9LxUe4ttaYvpQeNi/2wvPYdknvRmY.OnepfQAZafwnmzy', 'Tài xế 010', 'DRIVER', 1, '2026-10-13 17:34:49');

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
-- Đang đổ dữ liệu cho bảng `vehicles`
--

INSERT INTO `vehicles` (`vehicle_id`, `license_plate`, `vehicle_type`, `brand`, `capacity`, `status`, `created_at`, `updated_at`) VALUES
(1, '30K-123.45', 'Xe tải nhẹ', 'Hyundai', 1.50, 'available', '2026-10-07 11:16:53', '2026-10-13 17:33:37'),
(2, '51D-246.80', 'Xe tải nhẹ', 'Isuzu', 2.50, 'available', '2026-10-07 11:16:53', '2026-10-13 09:10:29'),
(3, '29H-567.89', 'Xe tải trung', 'Hino', 5.00, 'available', '2026-10-07 11:16:53', '2026-10-13 10:51:23'),
(4, '43C-135.79', 'Xe tải nặng', 'Thaco', 8.00, 'available', '2026-10-07 11:16:53', '2026-10-07 11:16:53'),
(5, '60H-864.20', 'Xe tải nhẹ', 'Kia', 1.00, 'available', '2026-10-07 11:16:53', '2026-10-13 16:18:49'),
(6, '50H-975.31', 'Xe van', 'Ford', 0.80, 'available', '2026-10-07 11:16:53', '2026-10-13 17:09:06'),
(7, '15C-112.23', 'Xe tải nặng', 'Hino', 15.00, 'available', '2026-10-07 11:16:53', '2026-10-13 17:51:29'),
(8, '37H-334.56', 'Xe tải trung', 'Hyundai', 3.50, 'available', '2026-10-07 11:16:53', '2026-10-12 19:16:02'),
(9, '29C-778.91', 'Xe tải nặng', 'Isuzu', 10.00, 'available', '2026-10-07 11:16:53', '2026-10-13 16:55:20');

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
  MODIFY `request_id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=24;

--
-- AUTO_INCREMENT cho bảng `customers`
--
ALTER TABLE `customers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT cho bảng `drivers`
--
ALTER TABLE `drivers`
  MODIFY `driver_id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'Khóa chính nội bộ của tài xế';

--
-- AUTO_INCREMENT cho bảng `driver_status_corrections`
--
ALTER TABLE `driver_status_corrections`
  MODIFY `correction_id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT cho bảng `orders`
--
ALTER TABLE `orders`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=108;

--
-- AUTO_INCREMENT cho bảng `trip_orders`
--
ALTER TABLE `trip_orders`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT cho bảng `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=45;

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
