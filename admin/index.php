<?php
/**
 * Front controller — the single entry point for the whole admin panel.
 * Every request (thanks to .htaccess) is routed through here.
 */

declare(strict_types=1);

// 1. Load configuration (DB credentials, paths, session settings)
require __DIR__ . '/config/config.php';

// 2. Simple PSR-4-ish autoloader: App\Core\Router -> app/Core/Router.php
spl_autoload_register(function (string $class): void {
    $prefix  = 'App\\';
    $baseDir = __DIR__ . '/app/';
    if (strncmp($class, $prefix, strlen($prefix)) !== 0) {
        return;
    }
    $relative = substr($class, strlen($prefix));
    $file     = $baseDir . str_replace('\\', '/', $relative) . '.php';
    if (is_file($file)) {
        require $file;
    }
});

// 3. Secure session start
session_name(SESSION_NAME);
session_start([
    'cookie_httponly' => true,
    'cookie_samesite' => 'Lax',
    'cookie_secure'   => SECURE_COOKIES, // set true once you have HTTPS
]);

// 4. Boot the router and register routes
use App\Core\Router;
use App\Controllers\AuthController;
use App\Controllers\PasswordResetController;
use App\Controllers\DashboardController;
use App\Controllers\UserController;
use App\Controllers\AccountController;
use App\Controllers\EnquiryController;
use App\Controllers\ClientController;
use App\Controllers\ProjectController;
use App\Controllers\HostingController;
use App\Controllers\BillController;
use App\Controllers\ReportController;

$router = new Router();

// Public routes
$router->get('/login',   [AuthController::class, 'showLogin']);
$router->post('/login',  [AuthController::class, 'login']);
$router->post('/logout', [AuthController::class, 'logout']);

// Forgot password (public - email lookup only, no reset mail is sent).
$router->get('/forgot-password',  [PasswordResetController::class, 'showForgot']);
$router->post('/forgot-password', [PasswordResetController::class, 'checkEmail']);
$router->get('/reset-password',   [PasswordResetController::class, 'showReset']);
$router->post('/reset-password',  [PasswordResetController::class, 'reset']);

// Protected routes (Auth checked inside the controller)
$router->get('/',        [DashboardController::class, 'index']);

// Enquiry Management (admins + managers; delete is admin-only in the controller)
$router->get('/enquiries',            [EnquiryController::class, 'index']);
$router->get('/enquiries/view',       [EnquiryController::class, 'show']);
$router->get('/enquiries/export',     [EnquiryController::class, 'exportPdf']);
$router->post('/enquiries/important', [EnquiryController::class, 'toggleImportant']);
$router->post('/enquiries/client',    [EnquiryController::class, 'toggleClient']);
$router->post('/enquiries/status',    [EnquiryController::class, 'updateStatus']);
$router->post('/enquiries/notes',     [EnquiryController::class, 'addNote']);
$router->post('/enquiries/delete',    [EnquiryController::class, 'destroy']);

// Client Management (admin-only — every action calls requireAdmin())
$router->get('/clients',                   [ClientController::class, 'index']);
$router->get('/clients/view',              [ClientController::class, 'show']);
$router->post('/clients/create',           [ClientController::class, 'store']);
$router->post('/clients/update',           [ClientController::class, 'update']);
$router->post('/clients/delete',           [ClientController::class, 'destroy']);
$router->post('/clients/meetings/create',  [ClientController::class, 'storeMeeting']);
$router->post('/clients/meetings/delete',  [ClientController::class, 'destroyMeeting']);
$router->post('/clients/invoices/create',  [ClientController::class, 'storeInvoice']);
$router->post('/clients/invoices/status',  [ClientController::class, 'updateInvoiceStatus']);
$router->post('/clients/invoices/delete',  [ClientController::class, 'destroyInvoice']);
$router->post('/clients/payments/create',  [ClientController::class, 'storePayment']);
$router->post('/clients/payments/delete',  [ClientController::class, 'destroyPayment']);

// Project Management (admins + managers; create/edit/delete restricted to admins in the controller)
$router->get('/projects',                 [ProjectController::class, 'index']);
$router->get('/projects/view',             [ProjectController::class, 'show']);
$router->post('/projects/create',          [ProjectController::class, 'store']);
$router->post('/projects/update',          [ProjectController::class, 'update']);
$router->post('/projects/delete',          [ProjectController::class, 'destroy']);
$router->post('/projects/tasks/create',    [ProjectController::class, 'storeTask']);
$router->post('/projects/tasks/update',    [ProjectController::class, 'updateTask']);
$router->post('/projects/tasks/status',    [ProjectController::class, 'updateTaskStatus']);
$router->post('/projects/tasks/delete',    [ProjectController::class, 'destroyTask']);
$router->post('/projects/tasks/notes',     [ProjectController::class, 'addTaskNote']);

// Billing (admin-only — every action calls requireAdmin())
$router->get('/bills',        [BillController::class, 'index']);
$router->get('/bills/view',   [BillController::class, 'show']);
$router->post('/bills/create', [BillController::class, 'store']);
$router->post('/bills/delete', [BillController::class, 'destroy']);

// Reports (admins + managers; each report re-checks the permission of the module it draws from)
$router->get('/reports',          [ReportController::class, 'index']);
$router->get('/reports/generate', [ReportController::class, 'generate']);

// Hosting & Domain Management (admin-only — every action calls requireAdmin())
$router->get('/hosting',                  [HostingController::class, 'index']);
$router->get('/hosting/view',             [HostingController::class, 'show']);
$router->post('/hosting/create',          [HostingController::class, 'store']);
$router->post('/hosting/update',          [HostingController::class, 'update']);
$router->post('/hosting/delete',          [HostingController::class, 'destroy']);
$router->post('/hosting/renew',           [HostingController::class, 'renew']);
$router->post('/hosting/renewals/delete', [HostingController::class, 'destroyRenewal']);

// Admin management (admin-only checks live inside the controller)
$router->get('/users',                 [UserController::class, 'index']);
$router->get('/users/edit',            [UserController::class, 'edit']);
$router->post('/users/update',         [UserController::class, 'update']);
$router->post('/users/create',         [UserController::class, 'store']);
$router->post('/users/reset-password', [UserController::class, 'resetPassword']);
$router->post('/users/delete',         [UserController::class, 'destroy']);

// My account (change own password — admins only, enforced in controller)
$router->get('/account',            [AccountController::class, 'index']);
$router->post('/account/password',  [AccountController::class, 'updatePassword']);
$router->post('/account/profile',   [AccountController::class, 'updateProfile']);
$router->get('/account/onboarding', [AccountController::class, 'onboarding']);
$router->post('/account/onboarding', [AccountController::class, 'storeOnboarding']);
$router->get('/account/document',   [AccountController::class, 'document']);

// 5. Dispatch
$router->dispatch(
    $_SERVER['REQUEST_METHOD'],
    $_SERVER['REQUEST_URI']
);
