<?php

namespace App\Http\Controllers;

use App\Core\Database;

class ReportController extends Controller
{
    public function salesSummary(): array
    {
        $pdo = Database::connection();
        $totalBookings = $pdo->query("SELECT IFNULL(SUM(amount),0) as total FROM bookings WHERE payment_status = 'paid'")->fetchColumn();
        $totalLeads = $pdo->query('SELECT COUNT(*) FROM leads')->fetchColumn();
        $totalCustomers = $pdo->query('SELECT COUNT(*) FROM customers')->fetchColumn();

        return [
            'data' => [
                'total_paid_booking_amount' => (float)$totalBookings,
                'total_leads' => (int)$totalLeads,
                'total_customers' => (int)$totalCustomers,
            ],
        ];
    }

    public function investmentSummary(): array
    {
        $pdo = Database::connection();
        $investorCount = $pdo->query('SELECT COUNT(*) FROM investors')->fetchColumn();
        $shares = $pdo->query('SELECT IFNULL(SUM(unit_price * quantity),0) FROM shares')->fetchColumn();
        $transactions = $pdo->query('SELECT IFNULL(SUM(amount),0) FROM transactions')->fetchColumn();

        return [
            'data' => [
                'investor_count' => (int)$investorCount,
                'share_capital' => (float)$shares,
                'total_transactions' => (float)$transactions,
            ],
        ];
    }

    public function financeStatement(): array
    {
        $pdo = Database::connection();
        $income = $pdo->query("SELECT IFNULL(SUM(amount),0) FROM accounts WHERE type = 'income'")->fetchColumn();
        $expense = $pdo->query("SELECT IFNULL(SUM(amount),0) FROM accounts WHERE type = 'expense'")->fetchColumn();

        return [
            'data' => [
                'total_income' => (float)$income,
                'total_expense' => (float)$expense,
                'net_balance' => (float)$income - (float)$expense,
            ],
        ];
    }
}
