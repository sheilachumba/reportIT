<?php

declare(strict_types=1);

session_start();

$config = require __DIR__ . '/../config/config.php';

date_default_timezone_set($config['app']['timezone'] ?? 'UTC');

require __DIR__ . '/../app/Core/Database.php';
require __DIR__ . '/../app/Core/View.php';
require __DIR__ . '/../app/Core/Controller.php';
require __DIR__ . '/../app/Core/Router.php';
require __DIR__ . '/../app/Core/Mailer.php';
require __DIR__ . '/../app/Controllers/IssueController.php';
require __DIR__ . '/../app/Controllers/ApiController.php';
require __DIR__ . '/../app/Controllers/HomeController.php';
require __DIR__ . '/../app/Controllers/AuthController.php';
require __DIR__ . '/../app/Controllers/AdminController.php';

$db = new App\Core\Database($config['db']);
$db->migrate();
$db->seedIfEmpty();

$router = new App\Core\Router($config, $db);

$router->get('/', [App\Controllers\HomeController::class, 'index']);

$router->get('/login', [App\Controllers\AuthController::class, 'showLogin']);
$router->post('/login', [App\Controllers\AuthController::class, 'login']);
$router->get('/register', [App\Controllers\AuthController::class, 'showRegister']);
$router->post('/register', [App\Controllers\AuthController::class, 'register']);
$router->get('/2fa', [App\Controllers\AuthController::class, 'show2fa']);
$router->post('/2fa', [App\Controllers\AuthController::class, 'verify2fa']);
$router->get('/logout', [App\Controllers\AuthController::class, 'logout']);

$router->get('/admin', [App\Controllers\AdminController::class, 'dashboard']);
$router->get('/admin/tickets', [App\Controllers\AdminController::class, 'tickets']);
$router->get('/admin/tickets/view', [App\Controllers\AdminController::class, 'ticketView']);
$router->post('/admin/tickets/update', [App\Controllers\AdminController::class, 'ticketUpdate']);
$router->get('/admin/attachment', [App\Controllers\AdminController::class, 'attachment']);

$router->get('/issue/new', [App\Controllers\IssueController::class, 'create']);
$router->post('/issue', [App\Controllers\IssueController::class, 'store']);
$router->get('/issue/confirm', [App\Controllers\IssueController::class, 'confirm']);

$router->get('/api/campuses', [App\Controllers\ApiController::class, 'campuses']);
$router->get('/api/buildings', [App\Controllers\ApiController::class, 'buildings']);
$router->get('/api/rooms', [App\Controllers\ApiController::class, 'rooms']);
$router->get('/api/devices', [App\Controllers\ApiController::class, 'devices']);

$router->dispatch($_SERVER['REQUEST_METHOD'] ?? 'GET', $_SERVER['REQUEST_URI'] ?? '/');
