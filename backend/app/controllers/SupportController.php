
<?php

require_once __DIR__ . '/../../core/Controller.php';
require_once __DIR__ . '/../models/SupportTicket.php';
require_once __DIR__ . '/../models/Customer.php';
require_once __DIR__ . '/../models/Order.php';
require_once __DIR__ . '/../models/User.php';
class SupportController extends Controller
{
    private $supportModel;
    private $customerModel;
    private $orderModel;

    private const CATEGORIES = [
        'CONSULTATION',
        'COMPLAINT',
        'INCIDENT',
        'FEEDBACK',
        'OTHER'
    ];

    private const STATUSES = [
        'PENDING',
        'PROCESSING',
        'RESOLVED',
        'CLOSED'
    ];

    public function __construct()
    {
        $this->supportModel = new SupportTicket();
        $this->customerModel = new Customer();
        $this->orderModel = new Order();
    }

    // Điều hướng các chức năng
    public function handle($action)
    {
        header('Cache-Control: no-store, private');

        switch ($action) {
            case 'customerIndex':
                $this->customerIndex();
                break;

            case 'customerCreate':
                $this->customerCreate();
                break;

            case 'customerShow':
                $this->customerShow();
                break;

            case 'customerReply':
                $this->customerReply();
                break;

            case 'staffIndex':
                $this->staffIndex();
                break;
            case 'staffShow':
                $this->staffShow();
                break;

            case 'staffReply':
                $this->staffReply();
                break;

            case 'staffStatus':
                $this->staffStatus();
                break;

            case 'adminAssign':
                $this->adminAssign();
                break;


            case 'adminAssign':
                $this->adminAssign();
                break;


            default:
                $this->error('Hành động hỗ trợ không hợp lệ.', 400);
        }
    }

    // Kiểm tra khách hàng đăng nhập
    private function requireCustomer()
    {
        if (empty($_SESSION['user_id'])) {
            $this->error('Vui lòng đăng nhập.', 401);
        }

        if (($_SESSION['role'] ?? '') !== 'CUSTOMER') {
            $this->error('Chức năng này chỉ dành cho khách hàng.', 403);
        }

        $customer = $this->customerModel->findByUserId(
            $_SESSION['user_id']
        );

        if (!$customer) {
            $this->error('Không tìm thấy hồ sơ khách hàng.', 404);
        }

        return $customer;
    }

    // Kiểm tra quyền nhân viên hỗ trợ
    private function requireStaff()
    {
        if (empty($_SESSION['user_id'])) {
            $this->error('Vui lòng đăng nhập.', 401);
        }

        $role = $_SESSION['role'] ?? '';

        if (!in_array(
            $role,
            ['ADMIN', 'TRANSPORT_STAFF', 'STAFF'],
            true
        )) {
            $this->error('Bạn không có quyền xử lý phiếu hỗ trợ.', 403);
        }
    }

    // Đọc JSON từ request
    private function readInput()
    {
        $data = json_decode(
            file_get_contents('php://input'),
            true
        );

        if (!is_array($data)) {
            $this->error('Dữ liệu gửi lên không hợp lệ.', 400);
        }

        return $data;
    }

    // Lấy danh sách phiếu của khách
    public function customerIndex()
    {
        $customer = $this->requireCustomer();

        $tickets = $this->supportModel->getByCustomerId(
            $customer['id']
        );

        $this->success(
            $tickets,
            'Lấy danh sách phiếu hỗ trợ thành công.'
        );
    }

    // Khách tạo phiếu hỗ trợ
    public function customerCreate()
    {
        $customer = $this->requireCustomer();
        $data = $this->readInput();

        $category = strtoupper(trim(
            $data['category'] ?? 'OTHER'
        ));

        $subject = trim($data['subject'] ?? '');
        $description = trim($data['description'] ?? '');
        $orderId = $data['order_id'] ?? null;

        if (!in_array($category, self::CATEGORIES, true)) {
            $this->error('Loại yêu cầu không hợp lệ.', 422);
        }

        if ($subject === '' || mb_strlen($subject) > 200) {
            $this->error(
                'Tiêu đề không được để trống và tối đa 200 ký tự.',
                422
            );
        }

        if ($description === '' || mb_strlen($description) > 5000) {
            $this->error(
                'Nội dung không được để trống và tối đa 5000 ký tự.',
                422
            );
        }

        // Nếu có mã đơn, xác minh đơn thuộc khách đang đăng nhập.
        if ($orderId !== null && $orderId !== '') {
            if (
                !is_scalar($orderId) ||
                !ctype_digit((string) $orderId) ||
                (int) $orderId < 1
            ) {
                $this->error('Mã đơn hàng không hợp lệ.', 422);
            }

            $orders = $this->orderModel->getByCustomerId(
                $customer['id']
            );

            $ownsOrder = false;

            foreach ($orders as $order) {
                if ((string) $order['id'] === (string) $orderId) {
                    $ownsOrder = true;
                    break;
                }
            }

            if (!$ownsOrder) {
                $this->error(
                    'Đơn hàng không tồn tại hoặc không thuộc tài khoản của bạn.',
                    422
                );
            }
        } else {
            $orderId = null;
        }

        try {
            $ticketCode = 'HT' . date('YmdHis')
                . random_int(100, 999);

            $ticketId = $this->supportModel->create([
                'ticket_code' => $ticketCode,
                'customer_id' => $customer['id'],
                'order_id' => $orderId,
                'category' => $category,
                'subject' => $subject,
                'description' => $description
            ]);

            // Lưu nội dung đầu tiên vào lịch sử trao đổi.
            $this->supportModel->addMessage(
                $ticketId,
                $_SESSION['user_id'],
                $description
            );

            $this->success([
                'id' => $ticketId,
                'ticket_code' => $ticketCode,
                'status' => 'PENDING'
            ], 'Gửi yêu cầu hỗ trợ thành công.');
        } catch (Throwable $e) {
            error_log('Support create error: ' . $e->getMessage());

            $this->error(
                'Không thể tạo phiếu hỗ trợ. Vui lòng thử lại.',
                500
            );
        }
    }

    // Khách xem chi tiết và lịch sử trao đổi
    public function customerShow()
    {
        $customer = $this->requireCustomer();
        $ticketId = $_GET['id'] ?? '';

        if (!ctype_digit((string) $ticketId) || (int) $ticketId < 1) {
            $this->error('Mã phiếu không hợp lệ.', 400);
        }

        if (!$this->supportModel->belongsToCustomer(
            $ticketId,
            $customer['id']
        )) {
            $this->error('Không tìm thấy phiếu hỗ trợ.', 404);
        }

        $ticket = $this->supportModel->findById($ticketId);
        $ticket['messages'] = $this->supportModel->getMessages(
            $ticketId
        );

        $this->success(
            $ticket,
            'Lấy chi tiết phiếu hỗ trợ thành công.'
        );
    }

    // Khách gửi thêm phản hồi
    public function customerReply()
    {
        $customer = $this->requireCustomer();
        $data = $this->readInput();

        $ticketId = $data['ticket_id'] ?? '';
        $message = trim($data['message'] ?? '');

        if (
            !is_scalar($ticketId) ||
            !ctype_digit((string) $ticketId) ||
            (int) $ticketId < 1
        ) {
            $this->error('Mã phiếu không hợp lệ.', 422);
        }

        if ($message === '' || mb_strlen($message) > 5000) {
            $this->error(
                'Nội dung phản hồi không được để trống và tối đa 5000 ký tự.',
                422
            );
        }

        if (!$this->supportModel->belongsToCustomer(
            $ticketId,
            $customer['id']
        )) {
            $this->error('Không tìm thấy phiếu hỗ trợ.', 404);
        }

        $ticket = $this->supportModel->findById($ticketId);

        if (in_array($ticket['status'], ['RESOLVED', 'CLOSED'], true)) {
            $this->error(
                'Phiếu đã được giải quyết hoặc đóng, không thể phản hồi thêm.',
                422
            );
        }

        try {
            $this->supportModel->addMessage(
                $ticketId,
                $_SESSION['user_id'],
                $message
            );

            $this->success(
                [],
                'Gửi phản hồi thành công.'
            );
        } catch (Throwable $e) {
            error_log('Customer support reply error: ' . $e->getMessage());

            $this->error('Không thể gửi phản hồi.', 500);
        }
    }

    // Nhân viên xem danh sách phiếu
    public function staffIndex()
    {
        $this->requireStaff();

        $status = strtoupper(trim($_GET['status'] ?? ''));

        if (
            $status !== '' &&
            !in_array($status, self::STATUSES, true)
        ) {
            $this->error('Trạng thái lọc không hợp lệ.', 422);
        }

        $tickets = $this->supportModel->getAll(
            $status === '' ? null : $status
        );

        $this->success(
            $tickets,
            'Lấy danh sách yêu cầu hỗ trợ thành công.'
        );
    }

    // Nhân viên phản hồi khách
    public function staffReply()
    {
        $this->requireStaff();
        $data = $this->readInput();

        $ticketId = $data['ticket_id'] ?? '';
        $message = trim($data['message'] ?? '');

        if (
            !is_scalar($ticketId) ||
            !ctype_digit((string) $ticketId) ||
            (int) $ticketId < 1
        ) {
            $this->error('Mã phiếu không hợp lệ.', 422);
        }

        if ($message === '' || mb_strlen($message) > 5000) {
            $this->error(
                'Nội dung phản hồi không được để trống và tối đa 5000 ký tự.',
                422
            );
        }

        $ticket = $this->supportModel->findById($ticketId);

        if (!$ticket) {
            $this->error('Không tìm thấy phiếu hỗ trợ.', 404);
        }

        if (in_array($ticket['status'], ['RESOLVED', 'CLOSED'], true)) {
            $this->error(
                'Phiếu đã giải quyết hoặc đóng. Không thể trả lời thêm.',
                422
            );
        }

        try {
            $this->supportModel->addMessage(
                $ticketId,
                $_SESSION['user_id'],
                $message
            );

            $this->success(
                [],
                'Đã gửi phản hồi cho khách hàng.'
            );
        } catch (Throwable $e) {
            error_log('Staff support reply error: ' . $e->getMessage());

            $this->error('Không thể gửi phản hồi.', 500);
        }
    }

    // Nhân viên cập nhật trạng thái
    public function staffStatus()
    {
        $this->requireStaff();
        $data = $this->readInput();

        $ticketId = $data['ticket_id'] ?? '';
        $status = strtoupper(trim($data['status'] ?? ''));

        if (
            !is_scalar($ticketId) ||
            !ctype_digit((string) $ticketId) ||
            (int) $ticketId < 1
        ) {
            $this->error('Mã phiếu không hợp lệ.', 422);
        }

        if (!in_array($status, self::STATUSES, true)) {
            $this->error('Trạng thái không hợp lệ.', 422);
        }

        $ticket = $this->supportModel->findById($ticketId);

        if (!$ticket) {
            $this->error('Không tìm thấy phiếu hỗ trợ.', 404);
        }

        if ($ticket['status'] === 'CLOSED') {
            $this->error('Phiếu đã đóng, không thể thay đổi.', 422);
        }

        if ($ticket['status'] === 'RESOLVED' && $status !== 'CLOSED') {
            $this->error(
                'Phiếu đã giải quyết chỉ có thể chuyển sang trạng thái đóng.',
                422
            );
        }

        try {
            $this->supportModel->updateStatus(
                $ticketId,
                $status,
                $_SESSION['user_id']
            );

            $this->success(
                ['status' => $status],
                'Cập nhật trạng thái phiếu thành công.'
            );
        } catch (Throwable $e) {
            error_log('Support status error: ' . $e->getMessage());

            $this->error('Không thể cập nhật trạng thái.', 500);
        }
    }

    // Nhân viên xem chi tiết phiếu và lịch sử trao đổi
    private function staffShow()
    {
        $this->requireStaff();

        $ticketId = $_GET['id'] ?? '';

        if (
            !ctype_digit((string) $ticketId) ||
            (int) $ticketId < 1
        ) {
            $this->error('ID phiếu hỗ trợ không hợp lệ.', 400);
        }

        $ticket = $this->supportModel->findById((int) $ticketId);

        if (!$ticket) {
            $this->error('Không tìm thấy phiếu hỗ trợ.', 404);
        }

        $messages = $this->supportModel->getMessages((int) $ticketId);

        $this->success([
            'ticket' => $ticket,
            'messages' => $messages
        ], 'Lấy chi tiết phiếu hỗ trợ thành công.');
    }

    private function adminAssign()
    {
        // Chỉ Admin được phân công
        if (
            empty($_SESSION['user_id']) ||
            ($_SESSION['role'] ?? '') !== 'ADMIN'
        ) {
            $this->error('Bạn không có quyền phân công phiếu hỗ trợ', 403);
            return;
        }

        $input = $this->readInput();

        $ticketId = filter_var(
            $input['ticket_id'] ?? null,
            FILTER_VALIDATE_INT
        );

        $staffId = filter_var(
            $input['staff_id'] ?? null,
            FILTER_VALIDATE_INT
        );

        if (!$ticketId || !$staffId) {
            $this->error('ticket_id hoặc staff_id không hợp lệ', 400);
            return;
        }

        // Kiểm tra phiếu có tồn tại
        $ticket = $this->supportModel->findById($ticketId);

        if (!$ticket) {
            $this->error('Không tìm thấy phiếu hỗ trợ', 404);
            return;
        }

        // Kiểm tra nhân viên được phân công
        $userModel = new User();
        $staff = $userModel->findById($staffId);

        if (
            !$staff ||
            ($staff['role'] ?? '') !== 'STAFF' ||
            (int)($staff['status'] ?? 0) !== 1
        ) {
            $this->error('Nhân viên không tồn tại, không hoạt động hoặc không hợp lệ', 400);
            return;
        }

        $this->supportModel->assignStaff($ticketId, $staffId);

        $this->success([], 'Phân công nhân viên thành công');
    }
}
