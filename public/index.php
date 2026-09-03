<?php
declare(strict_types=1);

require dirname(__DIR__) . '/src/config.php';
require dirname(__DIR__) . '/src/helpers.php';
require dirname(__DIR__) . '/src/Db.php';
require dirname(__DIR__) . '/src/Auth.php';
require dirname(__DIR__) . '/src/Access.php';
require dirname(__DIR__) . '/src/Activity.php';
require dirname(__DIR__) . '/src/Router.php';
require dirname(__DIR__) . '/src/Csv.php';
require dirname(__DIR__) . '/src/ImportMapper.php';
require dirname(__DIR__) . '/src/controllers/AuthController.php';
require dirname(__DIR__) . '/src/controllers/ClientController.php';
require dirname(__DIR__) . '/src/controllers/ImportController.php';
require dirname(__DIR__) . '/src/controllers/CourseController.php';
require dirname(__DIR__) . '/src/controllers/SessionLogController.php';
require dirname(__DIR__) . '/src/controllers/ContractController.php';
require dirname(__DIR__) . '/src/controllers/StaffController.php';
require dirname(__DIR__) . '/src/controllers/ActivityController.php';

session_start([
    'cookie_httponly' => true,
    'cookie_samesite' => 'Lax',
    'cookie_secure' => APP_ENV !== 'local',
]);

$router = new Router();

// -- Auth --------------------------------------------------------
$router->get('/login', [AuthController::class, 'showLogin']);
$router->post('/login', [AuthController::class, 'login']);
$router->post('/logout', [AuthController::class, 'logout']);
$router->get('/reset-password', [AuthController::class, 'showResetPassword']);
$router->post('/reset-password', [AuthController::class, 'resetPassword']);

// -- Clients -------------------------------------------------------
$router->get('/', fn () => redirect('/clients'));
$router->get('/clients', [ClientController::class, 'index']);
$router->get('/clients/new', [ClientController::class, 'create']);
$router->post('/clients', [ClientController::class, 'store']);
$router->get('/clients/import', [ImportController::class, 'showForm']);
$router->post('/clients/import/preview', [ImportController::class, 'preview']);
$router->post('/clients/import/commit', [ImportController::class, 'commit']);
$router->get('/clients/:id', [ClientController::class, 'show']);
$router->post('/clients/:id/course-records', [ClientController::class, 'storeCourseRecord']);
$router->post('/clients/:id/sessions', [SessionLogController::class, 'store']);
$router->post('/clients/:id/sessions/:logId/delete', [SessionLogController::class, 'destroy']);
$router->post('/clients/:id/contracts', [ContractController::class, 'store']);

// -- Contracts -------------------------------------------------------
$router->get('/contracts/expiring', [ContractController::class, 'expiryReview']);
$router->post('/contracts/:id/decide', [ContractController::class, 'decide']);

// -- Courses -------------------------------------------------------
$router->get('/courses', [CourseController::class, 'index']);
$router->post('/courses', [CourseController::class, 'store']);

// -- Staff (AA only) -------------------------------------------------------
$router->get('/staff', [StaffController::class, 'index']);
$router->post('/staff', [StaffController::class, 'store']);
$router->post('/staff/:id/toggle-status', [StaffController::class, 'toggleStatus']);
$router->post('/staff/assignments', [StaffController::class, 'assign']);
$router->post('/staff/assignments/:id/delete', [StaffController::class, 'unassign']);

// -- Activity log -------------------------------------------------------
$router->get('/activity', [ActivityController::class, 'index']);

$router->dispatch($_SERVER['REQUEST_METHOD'], $_SERVER['REQUEST_URI']);
