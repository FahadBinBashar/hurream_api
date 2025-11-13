<?php

use App\Core\Router;
use App\Http\Controllers\AccountController;
use App\Http\Controllers\ApprovalController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\BookingController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\InvestorController;
use App\Http\Controllers\LeadController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\ShareController;
use App\Http\Controllers\TransactionController;
use App\Http\Controllers\UserController;

return function (Router $router) {
    $router->group('/api', function (Router $router) {
        // Authentication
        $router->add('POST', '/auth/register', [AuthController::class, 'register']);
        $router->add('POST', '/auth/login', [AuthController::class, 'login']);
        $router->add('POST', '/auth/logout', [AuthController::class, 'logout'], ['auth']);

        // Users (Admin only)
        $router->add('GET', '/users', [UserController::class, 'index'], ['auth', 'role:admin']);
        $router->add('POST', '/users', [UserController::class, 'store'], ['auth', 'role:admin']);
        $router->add('GET', '/users/{id}', [UserController::class, 'show'], ['auth', 'role:admin']);
        $router->add('PATCH', '/users/{id}', [UserController::class, 'update'], ['auth', 'role:admin']);
        $router->add('DELETE', '/users/{id}', [UserController::class, 'destroy'], ['auth', 'role:admin']);

        // Customers
        $router->add('GET', '/customers', [CustomerController::class, 'index'], ['auth']);
        $router->add('POST', '/customers', [CustomerController::class, 'store'], ['auth']);
        $router->add('GET', '/customers/{id}', [CustomerController::class, 'show'], ['auth']);
        $router->add('PATCH', '/customers/{id}', [CustomerController::class, 'update'], ['auth']);
        $router->add('DELETE', '/customers/{id}', [CustomerController::class, 'destroy'], ['auth']);

        // Bookings
        $router->add('GET', '/bookings', [BookingController::class, 'index'], ['auth']);
        $router->add('POST', '/bookings', [BookingController::class, 'store'], ['auth']);
        $router->add('GET', '/bookings/{id}', [BookingController::class, 'show'], ['auth']);
        $router->add('PATCH', '/bookings/{id}', [BookingController::class, 'update'], ['auth']);
        $router->add('DELETE', '/bookings/{id}', [BookingController::class, 'destroy'], ['auth']);
        $router->add('POST', '/bookings/{id}/cancel', [BookingController::class, 'cancel'], ['auth']);

        // Investors & related
        $router->add('GET', '/investors', [InvestorController::class, 'index'], ['auth']);
        $router->add('POST', '/investors', [InvestorController::class, 'store'], ['auth']);
        $router->add('GET', '/investors/{id}', [InvestorController::class, 'show'], ['auth']);
        $router->add('PATCH', '/investors/{id}', [InvestorController::class, 'update'], ['auth']);
        $router->add('DELETE', '/investors/{id}', [InvestorController::class, 'destroy'], ['auth']);

        $router->add('GET', '/shares', [ShareController::class, 'index'], ['auth']);
        $router->add('POST', '/shares', [ShareController::class, 'store'], ['auth']);
        $router->add('GET', '/shares/{id}', [ShareController::class, 'show'], ['auth']);
        $router->add('PATCH', '/shares/{id}', [ShareController::class, 'update'], ['auth']);
        $router->add('DELETE', '/shares/{id}', [ShareController::class, 'destroy'], ['auth']);

        $router->add('GET', '/transactions', [TransactionController::class, 'index'], ['auth']);
        $router->add('POST', '/transactions', [TransactionController::class, 'store'], ['auth']);
        $router->add('GET', '/transactions/{id}', [TransactionController::class, 'show'], ['auth']);
        $router->add('PATCH', '/transactions/{id}', [TransactionController::class, 'update'], ['auth']);
        $router->add('DELETE', '/transactions/{id}', [TransactionController::class, 'destroy'], ['auth']);

        // HR
        $router->add('GET', '/employees', [EmployeeController::class, 'index'], ['auth']);
        $router->add('POST', '/employees', [EmployeeController::class, 'store'], ['auth']);
        $router->add('GET', '/employees/{id}', [EmployeeController::class, 'show'], ['auth']);
        $router->add('PATCH', '/employees/{id}', [EmployeeController::class, 'update'], ['auth']);
        $router->add('DELETE', '/employees/{id}', [EmployeeController::class, 'destroy'], ['auth']);

        // Sales & marketing
        $router->add('GET', '/leads', [LeadController::class, 'index'], ['auth']);
        $router->add('POST', '/leads', [LeadController::class, 'store'], ['auth']);
        $router->add('GET', '/leads/{id}', [LeadController::class, 'show'], ['auth']);
        $router->add('PATCH', '/leads/{id}', [LeadController::class, 'update'], ['auth']);
        $router->add('DELETE', '/leads/{id}', [LeadController::class, 'destroy'], ['auth']);

        // Approvals
        $router->add('GET', '/approvals', [ApprovalController::class, 'index'], ['auth']);
        $router->add('POST', '/approvals', [ApprovalController::class, 'store'], ['auth']);
        $router->add('GET', '/approvals/{id}', [ApprovalController::class, 'show'], ['auth']);
        $router->add('PATCH', '/approvals/{id}', [ApprovalController::class, 'update'], ['auth']);
        $router->add('DELETE', '/approvals/{id}', [ApprovalController::class, 'destroy'], ['auth']);

        // Accounts & finance
        $router->add('GET', '/accounts', [AccountController::class, 'index'], ['auth']);
        $router->add('POST', '/accounts', [AccountController::class, 'store'], ['auth']);
        $router->add('GET', '/accounts/{id}', [AccountController::class, 'show'], ['auth']);
        $router->add('PATCH', '/accounts/{id}', [AccountController::class, 'update'], ['auth']);
        $router->add('DELETE', '/accounts/{id}', [AccountController::class, 'destroy'], ['auth']);

        // Reports
        $router->add('GET', '/reports/sales-summary', [ReportController::class, 'salesSummary'], ['auth']);
        $router->add('GET', '/reports/investment-summary', [ReportController::class, 'investmentSummary'], ['auth']);
        $router->add('GET', '/reports/finance-statement', [ReportController::class, 'financeStatement'], ['auth']);
    });
};
