<?php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/Router.php';
require_once __DIR__ . '/../core/Controller.php';

/*
|--------------------------------------------------------------------------
| PHP Session
|--------------------------------------------------------------------------
*/

session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'secure' => false,
    'httponly' => true,
    'samesite' => 'Lax'
]);

session_start();

/*
|--------------------------------------------------------------------------
| Load routes
|--------------------------------------------------------------------------
*/

$router = require __DIR__ . '/../routes/api.php';

/*
|--------------------------------------------------------------------------
| Dispatch request
|--------------------------------------------------------------------------
*/

$path = $_GET['route'] ?? '/';

$router->dispatch(
    $_SERVER['REQUEST_METHOD'],
    $path
);