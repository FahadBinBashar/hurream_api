<?php

namespace App\Http\Controllers;

use App\Core\Database;
use App\Core\Request;

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
        $investorCount = $pdo->query('SELECT COUNT(*) FROM customers WHERE is_investor = 1')->fetchColumn();
        $shares = $pdo->query('SELECT IFNULL(SUM(amount),0) FROM customer_shares')->fetchColumn();
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

    public function cashBook(Request $request, array $params = [])
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare('SELECT v.date, SUM(l.debit) as debit, SUM(l.credit) as credit FROM voucher_lines l JOIN vouchers v ON v.id = l.voucher_id JOIN accounts a ON a.id = l.account_id WHERE v.date BETWEEN :from AND :to AND (LOWER(a.code) LIKE :code OR LOWER(a.name) LIKE :code) GROUP BY v.date ORDER BY v.date');
        $stmt->execute([
            'from' => $request->input('from') ?? '1970-01-01',
            'to' => $request->input('to') ?? date('Y-m-d'),
            'code' => 'cash%',
        ]);

        return $this->json(['data' => $stmt->fetchAll(\PDO::FETCH_ASSOC)]);
    }

    public function bankBook(Request $request, array $params = [])
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare('SELECT v.date, SUM(l.debit) as debit, SUM(l.credit) as credit FROM voucher_lines l JOIN vouchers v ON v.id = l.voucher_id JOIN accounts a ON a.id = l.account_id WHERE v.date BETWEEN :from AND :to AND (LOWER(a.code) LIKE :code OR LOWER(a.name) LIKE :code) GROUP BY v.date ORDER BY v.date');
        $stmt->execute([
            'from' => $request->input('from') ?? '1970-01-01',
            'to' => $request->input('to') ?? date('Y-m-d'),
            'code' => 'bank%',
        ]);

        return $this->json(['data' => $stmt->fetchAll(\PDO::FETCH_ASSOC)]);
    }

    public function ledger(Request $request, array $params = [])
    {
        if ($response = $this->validate($request, [
            'account_id' => 'required',
        ])) {
            return $response;
        }

        $pdo = Database::connection();
        $stmt = $pdo->prepare('SELECT v.date, v.voucher_no, l.debit, l.credit, l.description FROM voucher_lines l JOIN vouchers v ON v.id = l.voucher_id WHERE l.account_id = :account_id AND v.date BETWEEN :from AND :to ORDER BY v.date');
        $stmt->execute([
            'account_id' => $request->input('account_id'),
            'from' => $request->input('from') ?? '1970-01-01',
            'to' => $request->input('to') ?? date('Y-m-d'),
        ]);

        return $this->json(['data' => $stmt->fetchAll(\PDO::FETCH_ASSOC)]);
    }

    public function trialBalance(): array
    {
        $pdo = Database::connection();
        $sql = 'SELECT a.id, a.code, a.name, a.category, SUM(l.debit) as debit_total, SUM(l.credit) as credit_total FROM accounts a LEFT JOIN voucher_lines l ON l.account_id = a.id GROUP BY a.id, a.code, a.name, a.category ORDER BY a.code';
        $rows = $pdo->query($sql)->fetchAll(\PDO::FETCH_ASSOC);

        return ['data' => $rows];
    }

    public function incomeStatement(): array
    {
        $pdo = Database::connection();
        $income = $pdo->query("SELECT IFNULL(SUM(l.credit - l.debit),0) FROM voucher_lines l JOIN accounts a ON a.id = l.account_id WHERE a.category = 'income'")->fetchColumn();
        $expenses = $pdo->query("SELECT IFNULL(SUM(l.debit - l.credit),0) FROM voucher_lines l JOIN accounts a ON a.id = l.account_id WHERE a.category = 'expense'")->fetchColumn();

        return ['data' => [
            'income' => (float)$income,
            'expenses' => (float)$expenses,
            'net_profit' => (float)$income - (float)$expenses,
        ]];
    }

    public function balanceSheet(): array
    {
        $pdo = Database::connection();
        $assets = $pdo->query("SELECT IFNULL(SUM(l.debit - l.credit),0) FROM voucher_lines l JOIN accounts a ON a.id = l.account_id WHERE a.category = 'asset'")->fetchColumn();
        $liabilities = $pdo->query("SELECT IFNULL(SUM(l.credit - l.debit),0) FROM voucher_lines l JOIN accounts a ON a.id = l.account_id WHERE a.category = 'liability'")->fetchColumn();
        $equity = $pdo->query("SELECT IFNULL(SUM(l.credit - l.debit),0) FROM voucher_lines l JOIN accounts a ON a.id = l.account_id WHERE a.category = 'equity'")->fetchColumn();

        return ['data' => [
            'assets' => (float)$assets,
            'liabilities' => (float)$liabilities,
            'equity' => (float)$equity,
        ]];
    }
}
