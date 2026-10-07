<?php

require_once __DIR__ . '/../core/Router.php';
require_once __DIR__ . '/../app/controllers/AuthController.php';
require_once __DIR__ . '/../app/controllers/DriverController.php';

$router = new Router();
$authController = new AuthController();
$driverController = new DriverController();

$router->post('/api/login', function () use ($authController) {
    $authController->login();
});

$router->post('/api/logout', function () use ($authController) {
    $authController->logout();
});

$router->get('/api/me', function () use ($authController) {
    $authController->me();
});

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

return $router;
