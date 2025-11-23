<?php

namespace App\Http\Controllers;

use App\Core\Database;
use App\Core\Request;
use App\Models\Installment;
use App\Models\Project;
use App\Models\ShareSale;
use App\Models\ShareSalePayment;
use App\Models\Transaction;
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
        if (!empty($filters['related_type'])) {
            $conditions[] = 'related_type = :related_type';
            $params['related_type'] = $filters['related_type'];
        }
        if (!empty($filters['related_id'])) {
            $conditions[] = 'related_id = :related_id';
            $params['related_id'] = $filters['related_id'];
        }
        if (!empty($filters['from'])) {
            $conditions[] = 'due_date >= :from';
            $params['from'] = $filters['from'];
        }
        if (!empty($filters['to'])) {
            $conditions[] = 'due_date <= :to';
            $params['to'] = $filters['to'];
        }
        if (!empty($filters['customer_id'])) {
            $conditions[] = 'customer_id = :customer_id';
            $params['customer_id'] = $filters['customer_id'];
        }

        $sql = 'SELECT * FROM installments WHERE ' . implode(' AND ', $conditions) . ' ORDER BY due_date ASC';
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        return $this->json(['data' => $stmt->fetchAll(\PDO::FETCH_ASSOC)]);
    }

    public function due(Request $request)
    {
        return $this->index($request);
    }

    public function shareSaleInstallments(Request $request, array $params)
    {
        $sale = ShareSale::find((int)$params['id']);
        if (!$sale) {
            return $this->json(['message' => 'Sale not found'], 404);
        }

        $pdo = Database::connection();
        $stmt = $pdo->prepare('SELECT * FROM installments WHERE related_type = :type AND related_id = :id ORDER BY due_date');
        $stmt->execute(['type' => 'share_sale', 'id' => $sale['id']]);
        $installments = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $totals = [
            'total' => array_sum(array_column($installments, 'amount')),
            'paid' => array_sum(array_map(fn($item) => $item['status'] === 'paid' ? (float)$item['amount'] : 0, $installments)),
        ];
        $totals['due'] = $totals['total'] - $totals['paid'];

        $paymentStmt = $pdo->prepare('SELECT * FROM share_sale_payments WHERE share_sale_id = :sale ORDER BY received_at DESC');
        $paymentStmt->execute(['sale' => $sale['id']]);

        return $this->json([
            'data' => [
                'sale' => [
                    'id' => $sale['id'],
                    'customer_id' => $sale['customer_id'],
                    'project_id' => $sale['project_id'],
                    'total_amount' => $sale['total_amount'],
                    'down_payment' => $sale['down_payment'],
                    'installment_months' => $sale['installment_months'],
                    'installment_amount' => $sale['installment_amount'],
                    'payment_mode' => $sale['payment_mode'],
                ],
                'totals' => $totals,
                'installments' => $installments,
                'payments' => $paymentStmt->fetchAll(\PDO::FETCH_ASSOC),
            ],
        ]);
    }

    public function schedule(Request $request)
    {
        if ($response = $this->validate($request, [
            'sale_id' => 'required|numeric|min:1',
        ])) {
            return $response;
        }

        $pdo = Database::connection();
        $stmt = $pdo->prepare('SELECT * FROM installments WHERE related_type = :type AND related_id = :id ORDER BY due_date');
        $stmt->execute(['type' => 'share_sale', 'id' => $request->input('sale_id')]);

        return $this->json(['data' => $stmt->fetchAll(\PDO::FETCH_ASSOC)]);
    }

    public function store(Request $request)
    {
        if ($response = $this->validate($request, [
            'related_type' => 'required|in:customer_share,booking,share_sale',
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

        return $this->completePayment($installment, $request);
    }

    public function pay(Request $request)
    {
        if ($response = $this->validate($request, [
            'installment_id' => 'required|numeric|min:1',
        ])) {
            return $response;
        }

        $installment = Installment::find((int)$request->input('installment_id'));
        if (!$installment) {
            return $this->json(['message' => 'Installment not found'], 404);
        }

        return $this->completePayment($installment, $request);
    }

    public function payShareSaleInstallment(Request $request, array $params)
    {
        $saleId = (int)$params['sale_id'];
        $installmentId = (int)$params['installment_id'];

        $sale = ShareSale::find($saleId);
        if (!$sale) {
            return $this->json(['message' => 'Sale not found'], 404);
        }

        $installment = Installment::find($installmentId);
        if (!$installment || $installment['related_type'] !== 'share_sale' || (int)$installment['related_id'] !== $saleId) {
            return $this->json(['message' => 'Installment not found for this sale'], 404);
        }

        return $this->completePayment($installment, $request, $sale);
    }

    private function completePayment(array $installment, Request $request, ?array $sale = null)
    {
        if ($installment['status'] === 'paid') {
            return $this->json(['message' => 'Installment already paid'], 400);
        }

        $paymentPayload = $this->normalizePaymentPayload($request, $installment);
        if ($response = $this->validatePaymentPayload($paymentPayload)) {
            return $response;
        }

        $installmentAmount = (float)$installment['amount'];
        if ($paymentPayload['amount'] < $installmentAmount) {
            return $this->json(['message' => 'Payment must cover the full installment amount'], 422);
        }
        if ($paymentPayload['amount'] > $installmentAmount) {
            return $this->json(['message' => 'Payment exceeds installment amount'], 422);
        }

        $payload = [
            'status' => 'paid',
            'paid_at' => $paymentPayload['received_at'],
        ];
        Installment::update((int)$installment['id'], $payload);

        $voucher = Voucher::create([
            'voucher_no' => 'INST-' . $installment['id'] . '-' . time(),
            'voucher_type' => 'receipt',
            'date' => date('Y-m-d', strtotime($paymentPayload['received_at'])),
            'description' => 'Installment payment via ' . $paymentPayload['channel'],
            'created_by' => Auth::user()['id'] ?? null,
            'status' => 'approved',
        ]);

        VoucherLine::create([
            'voucher_id' => $voucher['id'],
            'account_id' => 1,
            'debit' => $paymentPayload['amount'],
            'credit' => 0,
            'description' => 'Payment received',
        ]);
        VoucherLine::create([
            'voucher_id' => $voucher['id'],
            'account_id' => 2,
            'debit' => 0,
            'credit' => $paymentPayload['amount'],
            'description' => 'Installment income',
        ]);

        if ($installment['related_type'] === 'share_sale') {
            $sale ??= ShareSale::find((int)$installment['related_id']);
            $this->recordShareSalePayment($sale, $installment, $paymentPayload);
        }

        AuditLogger::log(Auth::user(), 'update', 'finance', 'installment', (int)$installment['id'], $payload, $request->ip(), $request->userAgent());

        return $this->json(['message' => 'Installment marked as paid', 'voucher_id' => $voucher['id']]);
    }

    private function normalizePaymentPayload(Request $request, array $installment): array
    {
        $payment = $request->input('payment');
        $payment = is_array($payment) ? $payment : [];
        $receivedAt = $payment['received_at'] ?? date('Y-m-d H:i:s');

        return [
            'amount' => isset($payment['amount']) ? (float)$payment['amount'] : (float)$installment['amount'],
            'channel' => $payment['channel'] ?? 'cash',
            'reference_no' => $payment['reference_no'] ?? null,
            'bank_name' => $payment['bank_name'] ?? null,
            'gateway' => $payment['gateway'] ?? null,
            'transaction_id' => $payment['transaction_id'] ?? null,
            'note' => $payment['note'] ?? null,
            'received_at' => $receivedAt,
        ];
    }

    private function validatePaymentPayload(array $payload)
    {
        $rules = [
            'amount' => 'required|numeric|min:0.01',
            'channel' => 'required|in:cash,bank_transfer,sslcommerz,card,bkash',
            'reference_no' => '',
            'bank_name' => '',
            'gateway' => '',
            'transaction_id' => '',
            'note' => '',
            'received_at' => 'date',
        ];

        $errors = Validator::make($payload, $rules);
        if (!empty($errors)) {
            return $this->json(['message' => 'Payment validation failed', 'errors' => $errors], 422);
        }

        return null;
    }

    private function recordShareSalePayment(?array $sale, array $installment, array $paymentPayload): void
    {
        if (!$sale) {
            return;
        }

        $pdo = Database::connection();
        $stmt = $pdo->prepare('SELECT * FROM customer_shares WHERE sale_id = :sale LIMIT 1');
        $stmt->execute(['sale' => $sale['id']]);
        $share = $stmt->fetch(\PDO::FETCH_ASSOC) ?: null;

        if ($share) {
            Transaction::create([
                'share_id' => (int)$share['id'],
                'amount' => (float)$paymentPayload['amount'],
                'payment_type' => $paymentPayload['channel'],
                'date' => $paymentPayload['received_at'],
            ]);
        }

        $project = Project::find((int)$sale['project_id']);
        $receiptNo = $this->generateReceiptNo($project['project_code'] ?? null);

        ShareSalePayment::create([
            'share_sale_id' => (int)$sale['id'],
            'customer_id' => (int)$sale['customer_id'],
            'amount' => (float)$paymentPayload['amount'],
            'payment_channel' => $paymentPayload['channel'],
            'receipt_no' => $receiptNo,
            'reference_no' => $paymentPayload['reference_no'] ?? null,
            'bank_name' => $paymentPayload['bank_name'] ?? null,
            'gateway' => $paymentPayload['gateway'] ?? null,
            'transaction_id' => $paymentPayload['transaction_id'] ?? null,
            'metadata' => json_encode([
                'installment_id' => $installment['id'],
                'note' => $paymentPayload['note'] ?? null,
            ]),
            'received_at' => $paymentPayload['received_at'],
        ]);
    }

    private function generateReceiptNo(?string $projectCode): string
    {
        $prefix = $projectCode ? strtoupper($projectCode) : 'HRM';

        return sprintf('%s-INS-%s-%s', $prefix, date('YmdHis'), random_int(100, 999));
    }
}
