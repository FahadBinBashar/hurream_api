<?php

namespace App\Http\Controllers;

use App\Core\Database;
use App\Core\Request;
use App\Models\Installment;
use App\Models\Voucher;
use App\Models\VoucherLine;
use App\Support\AuditLogger;
use App\Support\Auth;
use App\Support\Validator;

class InstallmentController extends Controller
{
    public function index(Request $request)
    {
        $filters = $request->all();
        $pdo = Database::connection();
        $conditions = ['status != "paid"'];
        $params = [];
        if (!empty($filters['from'])) {
            $conditions[] = 'due_date >= :from';
            $params['from'] = $filters['from'];
        }
        if (!empty($filters['to'])) {
            $conditions[] = 'due_date <= :to';
            $params['to'] = $filters['to'];
        }

        $sql = 'SELECT * FROM installments WHERE ' . implode(' AND ', $conditions) . ' ORDER BY due_date ASC';
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        return $this->json(['data' => $stmt->fetchAll(\PDO::FETCH_ASSOC)]);
    }

    public function store(Request $request)
    {
        if ($response = $this->validate($request, [
            'related_type' => 'required|in:customer_share,booking',
            'related_id' => 'required|numeric',
            'due_date' => 'required|date',
            'amount' => 'required|numeric|min:0',
        ])) {
            return $response;
        }

        $payload = $request->all();
        $payload['status'] = $payload['status'] ?? 'pending';
        $installment = Installment::create($payload);

        AuditLogger::log(Auth::user(), 'create', 'finance', 'installment', (int)$installment['id'], $payload, $request->ip(), $request->userAgent());

        return $this->json(['data' => $installment], 201);
    }

    public function markPaid(Request $request, array $params)
    {
        $installment = Installment::find((int)$params['id']);
        if (!$installment) {
            return $this->json(['message' => 'Installment not found'], 404);
        }

        if ($installment['status'] === 'paid') {
            return $this->json(['message' => 'Installment already paid'], 400);
        }

        $payload = [
            'status' => 'paid',
            'paid_at' => date('Y-m-d H:i:s'),
        ];
        Installment::update((int)$installment['id'], $payload);

        $voucher = Voucher::create([
            'voucher_no' => 'INST-' . $installment['id'] . '-' . time(),
            'voucher_type' => 'receipt',
            'date' => date('Y-m-d'),
            'description' => 'Installment payment',
            'created_by' => Auth::user()['id'] ?? null,
            'status' => 'approved',
        ]);

        VoucherLine::create([
            'voucher_id' => $voucher['id'],
            'account_id' => 1,
            'debit' => $installment['amount'],
            'credit' => 0,
            'description' => 'Cash received',
        ]);
        VoucherLine::create([
            'voucher_id' => $voucher['id'],
            'account_id' => 2,
            'debit' => 0,
            'credit' => $installment['amount'],
            'description' => 'Installment income',
        ]);

        AuditLogger::log(Auth::user(), 'update', 'finance', 'installment', (int)$installment['id'], $payload, $request->ip(), $request->userAgent());

        return $this->json(['message' => 'Installment marked as paid', 'voucher_id' => $voucher['id']]);
    }
}
