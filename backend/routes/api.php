<?php

require_once __DIR__ . '/../core/Router.php';
require_once __DIR__ . '/../app/controllers/StatisticsController.php';
require_once __DIR__ . '/../app/controllers/TripController.php';
require_once __DIR__ . '/../app/controllers/AuthController.php';
require_once __DIR__ . '/../app/controllers/UserController.php';
require_once __DIR__ . '/../app/controllers/CustomerController.php';
require_once __DIR__ . '/../app/controllers/OrderController.php';
require_once __DIR__ . '/../app/controllers/DriverController.php';
require_once __DIR__ . '/../app/controllers/VehicleController.php';
require_once __DIR__ . '/../app/controllers/AssignmentController.php';
require_once __DIR__ . '/../app/controllers/SupportController.php';
$router = new Router();

/*
|--------------------------------------------------------------------------
| Authentication
|--------------------------------------------------------------------------
*/

$authController = new AuthController();

$router->post('/api/login', function () use ($authController) {
    $authController->login();
});

$router->post('/api/customer/register', function () use ($authController) {
    $authController->registerCustomer();
});

$router->post('/api/forgot-password', function () use ($authController) {
    $authController->forgotPassword();
});

$router->post('/api/logout', function () use ($authController) {
    $authController->logout();
});

$router->get('/api/me', function () use ($authController) {
    $authController->me();
});


/*
|--------------------------------------------------------------------------
| Users
|--------------------------------------------------------------------------
*/

$userController = new UserController();

$router->get('/api/users', function () use ($userController) {
    $userController->index();
});

$router->get('/api/users/show', function () use ($userController) {

    $id = $_GET['id'] ?? null;

    if (!$id) {
        http_response_code(400);

        header('Content-Type: application/json; charset=utf-8');

        echo json_encode([
            'success' => false,
            'message' => 'Thiếu ID người dùng'
        ], JSON_UNESCAPED_UNICODE);

        exit;
    }

    $userController->show($id);
});

$router->post('/api/users', function () use ($userController) {
    $userController->store();
});

$router->put('/api/users', function () use ($userController) {

    $id = $_GET['id'] ?? null;

    if (!$id) {
        http_response_code(400);

        header('Content-Type: application/json; charset=utf-8');

        echo json_encode([
            'success' => false,
            'message' => 'Thiếu ID người dùng'
        ], JSON_UNESCAPED_UNICODE);

        exit;
    }

    $userController->update($id);
});

$router->delete('/api/users', function () use ($userController) {

    $id = $_GET['id'] ?? null;

    if (!$id) {
        http_response_code(400);

        header('Content-Type: application/json; charset=utf-8');

        echo json_encode([
            'success' => false,
            'message' => 'Thiếu ID người dùng'
        ], JSON_UNESCAPED_UNICODE);

        exit;
    }

    $userController->destroy($id);
});


/*
|--------------------------------------------------------------------------
| Customers
|--------------------------------------------------------------------------
*/

$customerController = new CustomerController();

$router->get('/api/customers', function () use ($customerController) {
    $customerController->index();
});

$router->get('/api/customers/show', function () use ($customerController) {

    $id = $_GET['id'] ?? null;

    if (!$id) {
        http_response_code(400);

        header('Content-Type: application/json; charset=utf-8');

        echo json_encode([
            'success' => false,
            'message' => 'Thiếu ID khách hàng'
        ], JSON_UNESCAPED_UNICODE);

        exit;
    }

    $customerController->show($id);
});

$router->get('/api/customer/profile', function () use ($customerController) {
    $customerController->profile();
});

$router->post('/api/customers', function () use ($customerController) {
    $customerController->store();
});

$router->put('/api/customers', function () use ($customerController) {

    $id = $_GET['id'] ?? null;

    if (!$id) {
        http_response_code(400);

        header('Content-Type: application/json; charset=utf-8');

        echo json_encode([
            'success' => false,
            'message' => 'Thiếu ID khách hàng'
        ], JSON_UNESCAPED_UNICODE);

        exit;
    }

    $customerController->update($id);
});

$router->delete('/api/customers', function () use ($customerController) {

    $id = $_GET['id'] ?? null;

    if (!$id) {
        http_response_code(400);

        header('Content-Type: application/json; charset=utf-8');

        echo json_encode([
            'success' => false,
            'message' => 'Thiếu ID khách hàng'
        ], JSON_UNESCAPED_UNICODE);

        exit;
    }

    $customerController->destroy($id);
});


/*
|--------------------------------------------------------------------------
| Customer Orders
|--------------------------------------------------------------------------
*/
/*
|--------------------------------------------------------------------------
| Customer Orders
|--------------------------------------------------------------------------
*/
$orderController = new OrderController();

$router->get('/api/customer/orders', function () use ($orderController) {
    $orderController->handle('myOrders');
});

$router->post('/api/customer/orders', function () use ($orderController) {
    $orderController->handle('createCustomerOrder');
});

// BỔ SUNG: API SỬA ĐƠN HÀNG DÀNH CHO KHÁCH
$router->post('/api/customer/orders/update', function () use ($orderController) {
    $orderController->handle('update');
});

// BỔ SUNG: API HỦY ĐƠN HÀNG DÀNH CHO KHÁCH
$router->post('/api/customer/orders/cancel', function () use ($orderController) {
    $orderController->handle('cancel');
});

$router->post('/api/customer/orders/track', function () use ($orderController) {
    $orderController->handle('track');
});
$router->get('/api/orders', function () use ($orderController) {
    $orderController->handle('index');
});
$router->post('/api/orders/update-status', function () use ($orderController) {
    $orderController->handle('updateStatus');
});
$router->post('/api/orders/update-payment', function () use ($orderController) {
    $orderController->handle('updatePaymentStatus');
});
/*
|--------------------------------------------------------------------------
| Driver Management
|--------------------------------------------------------------------------
|
| Session and ADMIN/STAFF authorization are handled by DriverController.
|--------------------------------------------------------------------------
*/

$driverController = new DriverController();

$router->get('/api/drivers', function () use ($driverController) {
    $driverController->handle('index');
});

$router->get('/api/drivers/show', function () use ($driverController) {
    $driverController->handle('show');
});

$router->post('/api/drivers', function () use ($driverController) {
    $driverController->handle('store');
});

$router->put('/api/drivers', function () use ($driverController) {
    $driverController->handle('update');
});

$router->delete('/api/drivers', function () use ($driverController) {
    $driverController->handle('destroy');
});

$router->put('/api/drivers/status', function () use ($driverController) {
    $driverController->handle('status');
});

$vehicleController = new VehicleController();

$router->get('/api/vehicles', function () use ($vehicleController) {
    $vehicleController->handle('index');
});

$router->get('/api/vehicles/stats', function () use ($vehicleController) {
    $vehicleController->handle('stats');
});

$router->get('/api/vehicles/show', function () use ($vehicleController) {
    $vehicleController->handle('show');
});

$router->post('/api/vehicles', function () use ($vehicleController) {
    $vehicleController->handle('store');
});

$router->put('/api/vehicles', function () use ($vehicleController) {
    $vehicleController->handle('update');
});

$router->delete('/api/vehicles', function () use ($vehicleController) {
    $vehicleController->handle('destroy');
});

$assignmentController = new AssignmentController();

$router->get('/api/assignments', function () use ($assignmentController) {
    $assignmentController->handle('index');
});

$router->get('/api/assignments/show', function () use ($assignmentController) {
    $assignmentController->handle('show');
});

$router->get('/api/assignments/options', function () use ($assignmentController) {
    $assignmentController->handle('options');
});

$router->post('/api/assignments', function () use ($assignmentController) {
    $assignmentController->handle('store');
});

$router->put('/api/assignments', function () use ($assignmentController) {
    $assignmentController->handle('replace');
});

$router->put('/api/assignments/complete', function () use ($assignmentController) {
    $assignmentController->handle('complete');
});

$router->put('/api/assignments/cancel', function () use ($assignmentController) {
    $assignmentController->handle('cancel');
});

$tripController = new TripController();

$router->get('/api/trips', function () use ($tripController) {
    $tripController->handle('index');
});

$router->get('/api/trips/show', function () use ($tripController) {
    $tripController->handle('show');
});

$router->post('/api/trips', function () use ($tripController) {
    $tripController->handle('store');
});

$router->put('/api/trips', function () use ($tripController) {
    $tripController->handle('update');
});

$router->put('/api/trips/status', function () use ($tripController) {
    $tripController->handle('status');
});
$router->post('/api/trips/assign-order', function () use ($tripController) {
    $tripController->handle('assignOrder');
});
$router->delete('/api/trips', function () use ($tripController) {
    $tripController->handle('destroy');
});

$statisticsController = new StatisticsController();

$router->get('/api/statistics/overview', function () use ($statisticsController) {
    $statisticsController->handle('overview');
});

$supportController = new SupportController();

// Khách hàng
$router->get('/api/customer/support-tickets', function () use ($supportController) {
    $supportController->handle('customerIndex');
});

$router->post('/api/customer/support-tickets', function () use ($supportController) {
    $supportController->handle('customerCreate');
});

$router->get('/api/customer/support-tickets/show', function () use ($supportController) {
    $supportController->handle('customerShow');
});

$router->post('/api/customer/support-tickets/reply', function () use ($supportController) {
    $supportController->handle('customerReply');
});

// Nhân viên / Admin
$router->get('/api/staff/support-tickets', function () use ($supportController) {
    $supportController->handle('staffIndex');
});

$router->post('/api/staff/support-tickets/reply', function () use ($supportController) {
    $supportController->handle('staffReply');
});

$router->post('/api/staff/support-tickets/status', function () use ($supportController) {
    $supportController->handle('staffStatus');
});

$router->get('/api/staff/support-tickets/show', function () use ($supportController) {
    $supportController->handle('staffShow');
});

$router->post('/api/admin/support-tickets/assign', function () use ($supportController) {
    $supportController->handle('adminAssign');
});

return $router;
