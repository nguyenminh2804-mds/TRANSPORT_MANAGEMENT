<?php

require_once __DIR__ . '/../core/Router.php';

require_once __DIR__ . '/../app/controllers/AuthController.php';
require_once __DIR__ . '/../app/controllers/UserController.php';
require_once __DIR__ . '/../app/controllers/CustomerController.php';
require_once __DIR__ . '/../app/controllers/OrderController.php';

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

$orderController = new OrderController();

$router->get('/api/customer/orders', function () use ($orderController) {
    $orderController->myOrders();
});

$router->post('/api/customer/orders/track', function () use ($orderController) {
    $orderController->track();
});


return $router;