<?php

use App\Core\Router;
use App\Http\Controllers\AccountController;
use App\Http\Controllers\ApprovalController;
use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\BookingController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\DesignationController;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\GradeController;
use App\Http\Controllers\InstallmentController;
use App\Http\Controllers\LeadController;
use App\Http\Controllers\NotificationTemplateController;
use App\Http\Controllers\PayrollController;
use App\Http\Controllers\PolicyController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\SettingController;
use App\Http\Controllers\ShareController;
use App\Http\Controllers\StageController;
use App\Http\Controllers\TransactionController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\VoucherController;

$router = new Router();

// Authentication
$router->add('POST', '/auth/register', [AuthController::class, 'register']);
$router->add('POST', '/auth/login', [AuthController::class, 'login']);
$router->add('POST', '/auth/request-otp', [AuthController::class, 'requestOtp']);
$router->add('POST', '/auth/verify-otp', [AuthController::class, 'verifyOtp']);
$router->add('POST', '/auth/forgot-password', [AuthController::class, 'forgotPassword']);
$router->add('POST', '/auth/reset-password', [AuthController::class, 'resetPassword']);
$router->add('POST', '/auth/logout', [AuthController::class, 'logout'], ['auth']);

// Users (Admin only)
$router->add('GET', '/users', [UserController::class, 'index'], ['auth', 'role:admin']);
$router->add('POST', '/users', [UserController::class, 'store'], ['auth', 'role:admin']);
$router->add('GET', '/users/{id}', [UserController::class, 'show'], ['auth', 'role:admin']);
$router->add('PATCH', '/users/{id}', [UserController::class, 'update'], ['auth', 'role:admin']);
$router->add('DELETE', '/users/{id}', [UserController::class, 'destroy'], ['auth', 'role:admin']);

// Customers / Members
$router->add('GET', '/customers', [CustomerController::class, 'index'], ['auth']);
$router->add('POST', '/customers', [CustomerController::class, 'store'], ['auth']);
$router->add('GET', '/customers/{id}', [CustomerController::class, 'show'], ['auth']);
$router->add('PATCH', '/customers/{id}', [CustomerController::class, 'update'], ['auth']);
$router->add('DELETE', '/customers/{id}', [CustomerController::class, 'destroy'], ['auth']);
$router->add('POST', '/customers/{id}/verify-documents', [CustomerController::class, 'verifyDocuments'], ['auth']);

// Bookings
$router->add('GET', '/bookings', [BookingController::class, 'index'], ['auth']);
$router->add('POST', '/bookings', [BookingController::class, 'store'], ['auth']);
$router->add('GET', '/bookings/{id}', [BookingController::class, 'show'], ['auth']);
$router->add('PATCH', '/bookings/{id}', [BookingController::class, 'update'], ['auth']);
$router->add('DELETE', '/bookings/{id}', [BookingController::class, 'destroy'], ['auth']);
$router->add('POST', '/bookings/{id}/cancel', [BookingController::class, 'cancel'], ['auth']);

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
$router->add('GET', '/grades', [GradeController::class, 'index'], ['auth']);
$router->add('POST', '/grades', [GradeController::class, 'store'], ['auth']);
$router->add('PUT', '/grades/{id}', [GradeController::class, 'update'], ['auth']);
$router->add('GET', '/grades/{id}/designations', [DesignationController::class, 'byGrade'], ['auth']);
$router->add('GET', '/designations', [DesignationController::class, 'index'], ['auth']);
$router->add('POST', '/designations', [DesignationController::class, 'store'], ['auth']);
$router->add('PUT', '/designations/{id}', [DesignationController::class, 'update'], ['auth']);
$router->add('GET', '/employees', [EmployeeController::class, 'index'], ['auth']);
$router->add('POST', '/employees', [EmployeeController::class, 'store'], ['auth']);
$router->add('GET', '/employees/{id}', [EmployeeController::class, 'show'], ['auth']);
$router->add('PATCH', '/employees/{id}', [EmployeeController::class, 'update'], ['auth']);
$router->add('DELETE', '/employees/{id}', [EmployeeController::class, 'destroy'], ['auth']);
$router->add('POST', '/employees/{id}/attendance/check-in', [EmployeeController::class, 'checkIn'], ['auth']);
$router->add('POST', '/employees/{id}/attendance/check-out', [EmployeeController::class, 'checkOut'], ['auth']);
$router->add('GET', '/employees/{id}/attendance', [EmployeeController::class, 'attendance'], ['auth']);
$router->add('GET', '/employees/{id}/leave-requests', [EmployeeController::class, 'leaveIndex'], ['auth']);
$router->add('POST', '/employees/{id}/leave-requests', [EmployeeController::class, 'leaveStore'], ['auth']);
$router->add('PATCH', '/leave-requests/{leave_id}', [EmployeeController::class, 'leaveUpdate'], ['auth']);
$router->add('POST', '/leave-requests/{leave_id}/approve', [EmployeeController::class, 'leaveApprove'], ['auth']);
$router->add('DELETE', '/leave-requests/{leave_id}', [EmployeeController::class, 'leaveDestroy'], ['auth']);
$router->add('GET', '/employees/{id}/kpis', [EmployeeController::class, 'kpiIndex'], ['auth']);
$router->add('POST', '/employees/{id}/kpis', [EmployeeController::class, 'kpiStore'], ['auth']);
$router->add('POST', '/employees/{id}/verify-documents', [EmployeeController::class, 'verifyDocuments'], ['auth']);

// Sales & marketing
$router->add('GET', '/leads', [LeadController::class, 'index'], ['auth']);
$router->add('POST', '/leads', [LeadController::class, 'store'], ['auth']);
$router->add('GET', '/leads/{id}', [LeadController::class, 'show'], ['auth']);
$router->add('PATCH', '/leads/{id}', [LeadController::class, 'update'], ['auth']);
$router->add('DELETE', '/leads/{id}', [LeadController::class, 'destroy'], ['auth']);
$router->add('PUT', '/leads/{id}/convert-to-prospect', [LeadController::class, 'convertToProspect'], ['auth']);
$router->add('PUT', '/leads/{id}/convert-to-investor', [LeadController::class, 'convertToInvestor'], ['auth']);
$router->add('POST', '/leads/{id}/reminders', [LeadController::class, 'storeReminder'], ['auth']);
$router->add('GET', '/leads/reminders/upcoming', [LeadController::class, 'upcomingReminders'], ['auth']);
$router->add('GET', '/leads/reports/conversion', [LeadController::class, 'conversionReport'], ['auth']);
$router->add('GET', '/leads/reports/officer-summary', [LeadController::class, 'officerSummary'], ['auth']);

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

$router->add('GET', '/vouchers', [VoucherController::class, 'index'], ['auth']);
$router->add('POST', '/vouchers', [VoucherController::class, 'store'], ['auth']);
$router->add('GET', '/vouchers/{id}', [VoucherController::class, 'show'], ['auth']);

$router->add('GET', '/installments', [InstallmentController::class, 'index'], ['auth']);
$router->add('POST', '/installments', [InstallmentController::class, 'store'], ['auth']);
$router->add('POST', '/installments/{id}/mark-paid', [InstallmentController::class, 'markPaid'], ['auth']);

$router->add('GET', '/payroll/runs', [PayrollController::class, 'index'], ['auth']);
$router->add('POST', '/payroll/runs', [PayrollController::class, 'store'], ['auth']);

$router->add('POST', '/stages/close-period', [StageController::class, 'closePeriod'], ['auth']);
$router->add('GET', '/customers/{id}/stages', [StageController::class, 'customerStages'], ['auth']);
$router->add('PATCH', '/customers/{id}/stage', [StageController::class, 'upgrade'], ['auth']);
$router->add('GET', '/stages/report', [StageController::class, 'report'], ['auth']);

// Reports
$router->add('GET', '/reports/sales-summary', [ReportController::class, 'salesSummary'], ['auth']);
$router->add('GET', '/reports/investment-summary', [ReportController::class, 'investmentSummary'], ['auth']);
$router->add('GET', '/reports/finance-statement', [ReportController::class, 'financeStatement'], ['auth']);
$router->add('GET', '/reports/cash-book', [ReportController::class, 'cashBook'], ['auth']);
$router->add('GET', '/reports/bank-book', [ReportController::class, 'bankBook'], ['auth']);
$router->add('GET', '/reports/ledger', [ReportController::class, 'ledger'], ['auth']);
$router->add('GET', '/reports/trial-balance', [ReportController::class, 'trialBalance'], ['auth']);
$router->add('GET', '/reports/income-statement', [ReportController::class, 'incomeStatement'], ['auth']);
$router->add('GET', '/reports/balance-sheet', [ReportController::class, 'balanceSheet'], ['auth']);

$router->add('GET', '/audit-logs', [AuditLogController::class, 'index'], ['auth']);

$router->add('GET', '/policies', [PolicyController::class, 'index'], ['auth']);
$router->add('POST', '/policies', [PolicyController::class, 'store'], ['auth']);
$router->add('GET', '/policies/{id}', [PolicyController::class, 'show'], ['auth']);
$router->add('PATCH', '/policies/{id}', [PolicyController::class, 'update'], ['auth']);
$router->add('DELETE', '/policies/{id}', [PolicyController::class, 'destroy'], ['auth']);
$router->add('GET', '/policies/{id}/history', [PolicyController::class, 'history'], ['auth']);

$router->add('GET', '/settings', [SettingController::class, 'index'], ['auth']);
$router->add('PUT', '/settings/{key}', [SettingController::class, 'update'], ['auth']);

$router->add('GET', '/notification-templates', [NotificationTemplateController::class, 'index'], ['auth']);
$router->add('POST', '/notification-templates', [NotificationTemplateController::class, 'store'], ['auth']);
$router->add('GET', '/notification-templates/{id}', [NotificationTemplateController::class, 'show'], ['auth']);
$router->add('PATCH', '/notification-templates/{id}', [NotificationTemplateController::class, 'update'], ['auth']);
$router->add('DELETE', '/notification-templates/{id}', [NotificationTemplateController::class, 'destroy'], ['auth']);
$router->add('POST', '/notification-templates/{id}/render', [NotificationTemplateController::class, 'render'], ['auth']);
