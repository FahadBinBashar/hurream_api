<?php

namespace App\Http\Controllers;

use App\Core\Database;
use App\Core\Request;
use App\Models\Customer;
use App\Models\Project;
use App\Models\Share;
use App\Models\ShareSalePayment;
use App\Models\SharePackage;
use App\Models\ShareSale;
use App\Models\ShareSaleBatch;
use App\Models\Transaction;
use App\Models\Voucher;
use App\Models\VoucherLine;
use App\Services\CertificateGenerator;
use App\Services\InstallmentScheduleService;
use App\Services\NotificationService;
use App\Services\PackageBenefitService;
use App\Services\ReceiptInvoiceService;
use App\Services\ShareInventoryService;
use App\Support\AuditLogger;
use App\Support\Auth;
use App\Support\Env;
use App\Support\Validator;
use PDO;
use RuntimeException;
use Throwable;

class ShareSalesController extends Controller
{
    public function __construct(
        private readonly ShareInventoryService $inventoryService = new ShareInventoryService(),
        private readonly CertificateGenerator $certificateGenerator = new CertificateGenerator(),
        private readonly PackageBenefitService $benefitService = new PackageBenefitService(),
        private readonly InstallmentScheduleService $installmentService = new InstallmentScheduleService(),
        private readonly NotificationService $notificationService = new NotificationService(),
        private readonly ReceiptInvoiceService $documentService = new ReceiptInvoiceService()
    ) {
    }

    public function index(Request $request, array $params = [])
    {
        $pdo = Database::connection();
        $conditions = [];
        $bindings = [];

        foreach (['project_id', 'customer_id'] as $filter) {
            if ($value = $request->input($filter)) {
                $conditions[] = $filter . ' = :' . $filter;
                $bindings[$filter] = $value;
            }
        }
        if ($saleType = $request->input('sale_type')) {
            $conditions[] = 'sale_type = :sale_type';
            $bindings['sale_type'] = $saleType;
        }

        $sql = 'SELECT * FROM share_sales';
        if ($conditions) {
            $sql .= ' WHERE ' . implode(' AND ', $conditions);
        }
        $sql .= ' ORDER BY created_at DESC';

        $stmt = $pdo->prepare($sql);
        $stmt->execute($bindings);

        return $this->json(['data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
    }

    public function show(Request $request, array $params)
    {
        $sale = ShareSale::find((int)$params['id']);
        if (!$sale) {
            return $this->json(['message' => 'Sale not found'], 404);
        }

        $pdo = Database::connection();
        $stmt = $pdo->prepare('SELECT * FROM share_sale_batches WHERE share_sale_id = :id ORDER BY certificate_from');
        $stmt->execute(['id' => $sale['id']]);
        $sale['batches'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $shareStmt = $pdo->prepare('SELECT * FROM customer_shares WHERE sale_id = :id LIMIT 1');
        $shareStmt->execute(['id' => $sale['id']]);
        $sale['customer_share'] = $shareStmt->fetch(PDO::FETCH_ASSOC) ?: null;

        return $this->json(['data' => $sale]);
    }

    public function payments(Request $request, array $params)
    {
        $sale = ShareSale::find((int)$params['id']);
        if (!$sale) {
            return $this->json(['message' => 'Sale not found'], 404);
        }

        $pdo = Database::connection();
        $stmt = $pdo->prepare('SELECT * FROM share_sale_payments WHERE share_sale_id = :sale ORDER BY received_at DESC');
        $stmt->execute(['sale' => $sale['id']]);
        $payments = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $totalPaid = array_sum(array_map(fn ($payment) => (float)($payment['amount'] ?? 0), $payments));
        $remaining = max(0, (float)$sale['total_amount'] - $totalPaid);

        return $this->json([
            'data' => [
                'sale' => [
                    'id' => $sale['id'],
                    'customer_id' => $sale['customer_id'],
                    'project_id' => $sale['project_id'],
                    'total_amount' => (float)$sale['total_amount'],
                    'down_payment' => (float)$sale['down_payment'],
                    'payment_mode' => $sale['payment_mode'],
                ],
                'totals' => [
                    'paid' => $totalPaid,
                    'remaining' => $remaining,
                ],
                'payments' => $payments,
            ],
        ]);
    }

    public function destroy(Request $request, array $params)
    {
        if ($response = $this->ensureRole(['admin'])) {
            return $response;
        }

        $sale = ShareSale::find((int)$params['id']);
        if (!$sale) {
            return $this->json(['message' => 'Sale not found'], 404);
        }

        $pdo = Database::connection();
        $pdo->beginTransaction();

        try {
            $batchStmt = $pdo->prepare('SELECT * FROM share_sale_batches WHERE share_sale_id = :id FOR UPDATE');
            $batchStmt->execute(['id' => $sale['id']]);
            $allocations = $batchStmt->fetchAll(PDO::FETCH_ASSOC);

            $this->inventoryService->release($pdo, $allocations);

            $pdo->prepare('DELETE FROM share_sale_batches WHERE share_sale_id = :id')->execute(['id' => $sale['id']]);
            $pdo->prepare('DELETE FROM customer_shares WHERE sale_id = :id')->execute(['id' => $sale['id']]);
            $pdo->prepare('DELETE FROM installments WHERE related_type = :type AND related_id = :id')->execute([
                'type' => 'share_sale',
                'id' => $sale['id'],
            ]);
            ShareSale::delete((int)$sale['id']);

            $pdo->commit();
        } catch (Throwable $exception) {
            $pdo->rollBack();
            throw $exception;
        }

        AuditLogger::log(Auth::user(), 'delete', 'share_sales', 'share_sale', (int)$sale['id'], [], $request->ip(), $request->userAgent());

        return $this->json(['message' => 'Sale deleted']);
    }

    public function sellSingle(Request $request, array $params)
    {
        $routeProjectId = (int)$params['project_id'];
        $originalPayload = $request->all();
        if (isset($originalPayload['project_id']) && (int)$originalPayload['project_id'] !== $routeProjectId) {
            return $this->json(['message' => 'Project mismatch for sale request'], 422);
        }

        $payload = array_merge($originalPayload, ['project_id' => $routeProjectId]);

        return $this->processSingleSale($payload, $request, 'api');
    }

    public function sellPackage(Request $request, array $params)
    {
        $routeProjectId = (int)$params['project_id'];
        $originalPayload = $request->all();
        if (isset($originalPayload['project_id']) && (int)$originalPayload['project_id'] !== $routeProjectId) {
            return $this->json(['message' => 'Project mismatch for sale request'], 422);
        }

        $payload = array_merge($originalPayload, ['project_id' => $routeProjectId]);

        return $this->processPackageSale($payload, $request, 'api');
    }

    public function sellSingleSelf(Request $request, array $params = [])
    {
        $customerId = $this->authenticatedCustomerId();
        if ($customerId === null) {
            return $this->json(['message' => 'Customer authentication required'], 403);
        }

        $payload = array_merge($request->all(), ['customer_id' => $customerId]);

        if (empty($payload['project_id'])) {
            return $this->json(['message' => 'Project selection is required'], 422);
        }

        return $this->processSingleSale($payload, $request, 'self');
    }

    public function sellPackageSelf(Request $request, array $params = [])
    {
        $customerId = $this->authenticatedCustomerId();
        if ($customerId === null) {
            return $this->json(['message' => 'Customer authentication required'], 403);
        }

        $payload = array_merge($request->all(), ['customer_id' => $customerId]);

        if (empty($payload['project_id'])) {
            return $this->json(['message' => 'Project selection is required'], 422);
        }

        return $this->processPackageSale($payload, $request, 'self');
    }

    private function processSingleSale(array $payload, Request $request, string $saleSource)
    {
        $paymentPayload = $this->preparePaymentPayload($payload);
        if ($response = $this->validatePaymentPayload($paymentPayload)) {
            return $response;
        }

        if ($response = $this->validateSingleSalePayload($payload)) {
            return $response;
        }

        $customer = Customer::find((int)$payload['customer_id']);
        if (!$customer) {
            return $this->json(['message' => 'Customer not found'], 404);
        }

        $project = Project::find((int)$payload['project_id']);
        if (!$project) {
            return $this->json(['message' => 'Project not found'], 404);
        }

        $unitPrice = isset($payload['share_price'])
            ? (float)$payload['share_price']
            : (float)Env::get('SHARE_DEFAULT_UNIT_PRICE', 25000);
        $shareUnits = (int)$payload['share_units'];
        $totalAmount = $unitPrice * $shareUnits;
        $downPayment = isset($payload['down_payment'])
            ? (float)$payload['down_payment']
            : ($payload['payment_mode'] === 'installment' ? 0 : $totalAmount);
        if ($downPayment > $totalAmount) {
            return $this->json(['message' => 'Down payment cannot exceed total amount'], 422);
        }

        $installmentMonths = (int)($payload['installment_months'] ?? 0);
        if ($payload['payment_mode'] === 'one_time') {
            $installmentMonths = 0;
        } elseif ($installmentMonths <= 0) {
            return $this->json(['message' => 'Installment months required for installment purchases'], 422);
        }

        $remaining = max($totalAmount - $downPayment, 0);
        $monthlyInstallment = $installmentMonths > 0 ? round($remaining / $installmentMonths, 2) : 0;
        $stage = $payload['stage'] ?? 'MÖW-1';
        if ($paymentPayload && (float)$paymentPayload['amount'] > $totalAmount) {
            return $this->json(['message' => 'Payment amount cannot exceed total amount'], 422);
        }

        $pdo = Database::connection();
        $pdo->beginTransaction();

        try {
            $allocations = $this->inventoryService->reserve($pdo, (int)$project['id'], $shareUnits);
            $certificate = $this->certificateGenerator->fromAllocations($allocations, $project['certificate_prefix'] ?? $project['project_code'] ?? 'HRM');

            $sale = ShareSale::create([
                'customer_id' => (int)$customer['id'],
                'project_id' => (int)$project['id'],
                'package_id' => null,
                'sale_type' => 'single',
                'total_shares' => $shareUnits,
                'bonus_shares' => 0,
                'share_price' => $unitPrice,
                'total_amount' => $totalAmount,
                'down_payment' => $downPayment,
                'installment_months' => $installmentMonths,
                'installment_amount' => $monthlyInstallment,
                'payment_mode' => $payload['payment_mode'],
                'invoice_no' => $this->generateInvoiceNo($project['project_code']),
                'certificate_no' => $certificate['certificate_no'],
                'certificate_start' => $certificate['certificate_start'],
                'certificate_end' => $certificate['certificate_end'],
                'status' => 'completed',
                'sale_source' => $saleSource,
                'metadata' => json_encode(['allocations' => $allocations, 'stage' => $stage]),
            ]);

            foreach ($allocations as $allocation) {
                ShareSaleBatch::create([
                    'share_sale_id' => (int)$sale['id'],
                    'batch_id' => $allocation['batch_id'],
                    'shares_deducted' => $allocation['shares'],
                    'certificate_from' => $allocation['certificate_from'],
                    'certificate_to' => $allocation['certificate_to'],
                    'share_price' => $allocation['share_price'],
                ]);
            }

            $shareRecord = Share::create([
                'customer_id' => (int)$customer['id'],
                'project_id' => (int)$project['id'],
                'sale_id' => (int)$sale['id'],
                'primary_batch_id' => $allocations[0]['batch_id'] ?? null,
                'share_type' => 'single',
                'package_id' => null,
                'unit_price' => $unitPrice,
                'share_units' => $shareUnits,
                'bonus_units' => 0,
                'total_units' => $shareUnits,
                'amount' => $totalAmount,
                'down_payment' => $downPayment,
                'monthly_installment' => $monthlyInstallment ?: null,
                'installment_months' => $installmentMonths ?: null,
                'payment_mode' => $payload['payment_mode'],
                'stage' => $stage,
                'reinvest_flag' => 0,
                'status' => 'active',
                'approval_status' => 'approved',
                'approver_gate_triggered' => $totalAmount >= (float)Env::get('SHARE_MULTI_APPROVAL_THRESHOLD', 500000) ? 1 : 0,
                'benefits_snapshot' => null,
                'certificate_no' => $certificate['certificate_no'],
                'invoice_no' => $sale['invoice_no'],
            ]);

            if ($installmentMonths > 0 && $remaining > 0) {
                $this->installmentService->buildForSale(
                    (int)$sale['id'],
                    (int)$customer['id'],
                    (int)$project['id'],
                    $remaining,
                    $installmentMonths,
                    date('Y-m-d')
                );
            }

            $pdo->commit();
        } catch (RuntimeException $exception) {
            $pdo->rollBack();
            return $this->json(['message' => $exception->getMessage()], 422);
        } catch (Throwable $exception) {
            $pdo->rollBack();
            throw $exception;
        }

        $paymentRecord = null;
        if ($paymentPayload) {
            $paymentRecord = $this->recordSalePayment($sale, $shareRecord, $paymentPayload);
        }

        $this->notificationService->sendSaleConfirmation($sale, $customer);
        AuditLogger::log(Auth::user(), 'create', 'share_sales', 'share_sale', (int)$sale['id'], $sale, $request->ip(), $request->userAgent());

        return $this->json([
            'data' => [
                'sale' => $sale,
                'share' => $shareRecord,
                'allocations' => $allocations,
                'payment' => $paymentRecord,
            ],
        ], 201);
    }

    private function processPackageSale(array $payload, Request $request, string $saleSource)
    {
        $paymentPayload = $this->preparePaymentPayload($payload);
        if ($response = $this->validatePaymentPayload($paymentPayload)) {
            return $response;
        }

        if ($response = $this->validatePackageSalePayload($payload)) {
            return $response;
        }

        $customer = Customer::find((int)$payload['customer_id']);
        if (!$customer) {
            return $this->json(['message' => 'Customer not found'], 404);
        }

        $package = SharePackage::find((int)$payload['package_id']);
        if (!$package) {
            return $this->json(['message' => 'Package not found'], 404);
        }
        if (($package['status'] ?? 'inactive') !== 'active') {
            return $this->json(['message' => 'Package is inactive'], 422);
        }

        if ((int)$package['project_id'] !== (int)$payload['project_id']) {
            return $this->json(['message' => 'Package does not belong to the selected project'], 422);
        }

        $project = Project::find((int)$package['project_id']);
        if (!$project) {
            return $this->json(['message' => 'Project not found'], 404);
        }

        $shareUnits = (int)$package['total_shares_included'];
        $bonusUnits = (int)$package['bonus_shares'];
        $totalUnits = $shareUnits + $bonusUnits;
        $packagePrice = (float)$package['package_price'];
        $downPayment = isset($payload['down_payment'])
            ? (float)$payload['down_payment']
            : (float)($package['down_payment'] ?? 0);
        if ($downPayment > $packagePrice) {
            return $this->json(['message' => 'Down payment cannot exceed package price'], 422);
        }

        $installmentMonths = (int)($payload['installment_months'] ?? $package['installment_months'] ?? 0);
        if ($payload['payment_mode'] === 'one_time') {
            $installmentMonths = 0;
        } elseif ($installmentMonths <= 0) {
            return $this->json(['message' => 'Installment months required for installment purchases'], 422);
        }

        $remaining = max($packagePrice - $downPayment, 0);
        $monthlyInstallment = $installmentMonths > 0 ? round($remaining / max(1, $installmentMonths), 2) : 0;
        $benefits = $this->benefitService->snapshot($package);
        $stage = $payload['stage'] ?? 'MÖW-1';
        if ($paymentPayload && (float)$paymentPayload['amount'] > $packagePrice) {
            return $this->json(['message' => 'Payment amount cannot exceed total package price'], 422);
        }

        $pdo = Database::connection();
        $pdo->beginTransaction();

        try {
            $allocations = $this->inventoryService->reserve($pdo, (int)$project['id'], $totalUnits);
            $certificate = $this->certificateGenerator->fromAllocations($allocations, $project['certificate_prefix'] ?? $project['project_code'] ?? 'HRM');

            $sale = ShareSale::create([
                'customer_id' => (int)$customer['id'],
                'project_id' => (int)$project['id'],
                'package_id' => (int)$package['id'],
                'sale_type' => 'package',
                'total_shares' => $shareUnits,
                'bonus_shares' => $bonusUnits,
                'share_price' => $packagePrice,
                'total_amount' => $packagePrice,
                'down_payment' => $downPayment,
                'installment_months' => $installmentMonths,
                'installment_amount' => $monthlyInstallment,
                'payment_mode' => $payload['payment_mode'],
                'invoice_no' => $this->generateInvoiceNo($project['project_code']),
                'certificate_no' => $certificate['certificate_no'],
                'certificate_start' => $certificate['certificate_start'],
                'certificate_end' => $certificate['certificate_end'],
                'status' => 'completed',
                'sale_source' => $saleSource,
                'metadata' => json_encode(['benefits' => $benefits, 'allocations' => $allocations, 'stage' => $stage]),
            ]);

            foreach ($allocations as $allocation) {
                ShareSaleBatch::create([
                    'share_sale_id' => (int)$sale['id'],
                    'batch_id' => $allocation['batch_id'],
                    'shares_deducted' => $allocation['shares'],
                    'certificate_from' => $allocation['certificate_from'],
                    'certificate_to' => $allocation['certificate_to'],
                    'share_price' => $allocation['share_price'],
                ]);
            }

            $shareRecord = Share::create([
                'customer_id' => (int)$customer['id'],
                'project_id' => (int)$project['id'],
                'sale_id' => (int)$sale['id'],
                'primary_batch_id' => $allocations[0]['batch_id'] ?? null,
                'share_type' => 'package',
                'package_id' => (int)$package['id'],
                'unit_price' => $totalUnits > 0 ? round($packagePrice / $totalUnits, 2) : $packagePrice,
                'share_units' => $shareUnits,
                'bonus_units' => $bonusUnits,
                'total_units' => $totalUnits,
                'amount' => $packagePrice,
                'down_payment' => $downPayment,
                'monthly_installment' => $monthlyInstallment ?: null,
                'installment_months' => $installmentMonths ?: null,
                'payment_mode' => $payload['payment_mode'],
                'stage' => $stage,
                'reinvest_flag' => 0,
                'status' => 'active',
                'approval_status' => 'approved',
                'approver_gate_triggered' => $packagePrice >= (float)Env::get('SHARE_MULTI_APPROVAL_THRESHOLD', 500000) ? 1 : 0,
                'benefits_snapshot' => json_encode($benefits),
                'certificate_no' => $certificate['certificate_no'],
                'invoice_no' => $sale['invoice_no'],
            ]);

            if ($installmentMonths > 0 && $remaining > 0) {
                $this->installmentService->buildForSale(
                    (int)$sale['id'],
                    (int)$customer['id'],
                    (int)$project['id'],
                    $remaining,
                    $installmentMonths,
                    date('Y-m-d')
                );
            }

            $pdo->commit();
        } catch (RuntimeException $exception) {
            $pdo->rollBack();
            return $this->json(['message' => $exception->getMessage()], 422);
        } catch (Throwable $exception) {
            $pdo->rollBack();
            throw $exception;
        }

        $paymentRecord = null;
        if ($paymentPayload) {
            $paymentRecord = $this->recordSalePayment($sale, $shareRecord, $paymentPayload);
        }

        $this->notificationService->sendSaleConfirmation($sale, $customer);
        AuditLogger::log(Auth::user(), 'create', 'share_sales', 'share_sale', (int)$sale['id'], $sale, $request->ip(), $request->userAgent());

        return $this->json([
            'data' => [
                'sale' => $sale,
                'share' => $shareRecord,
                'allocations' => $allocations,
                'benefits' => $benefits,
                'payment' => $paymentRecord,
            ],
        ], 201);
    }

    public function addPayment(Request $request, array $params)
    {
        $saleId = (int)$params['id'];
        $sale = ShareSale::find($saleId);
        if (!$sale) {
            return $this->json(['message' => 'Sale not found'], 404);
        }

        $paymentPayload = $this->preparePaymentPayload($request->all());
        if ($response = $this->validatePaymentPayload($paymentPayload)) {
            return $response;
        }
        if (!$paymentPayload) {
            return $this->json(['message' => 'Payment details are required'], 422);
        }

        $pdo = Database::connection();
        $stmt = $pdo->prepare('SELECT * FROM customer_shares WHERE sale_id = :sale LIMIT 1');
        $stmt->execute(['sale' => $saleId]);
        $share = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        if (!$share) {
            return $this->json(['message' => 'Customer share not found for sale'], 404);
        }

        $paidStmt = $pdo->prepare('SELECT COALESCE(SUM(amount), 0) AS total_paid FROM share_sale_payments WHERE share_sale_id = :sale');
        $paidStmt->execute(['sale' => $saleId]);
        $totalPaid = (float)$paidStmt->fetchColumn();
        $totalAmount = (float)$sale['total_amount'];
        if ((float)$paymentPayload['amount'] + $totalPaid > $totalAmount) {
            return $this->json(['message' => 'Payment exceeds remaining balance'], 422);
        }

        $paymentRecord = $this->recordSalePayment($sale, $share, $paymentPayload);

        return $this->json(['data' => $paymentRecord], 201);
    }

    private function validateSingleSalePayload(array $payload)
    {
        $rules = [
            'customer_id' => 'required|numeric|min:1',
            'project_id' => 'required|numeric|min:1',
            'share_units' => 'required|numeric|min:1',
            'share_price' => 'numeric|min:1',
            'payment_mode' => 'required|in:one_time,installment',
            'down_payment' => 'numeric|min:0',
            'installment_months' => 'numeric|min:1',
            'stage' => 'regex:/^MÖW-\\d+$/u',
        ];

        $errors = Validator::make($payload, $rules);
        if (!empty($errors)) {
            return $this->json(['message' => 'Validation failed', 'errors' => $errors], 422);
        }

        return null;
    }

    private function validatePackageSalePayload(array $payload)
    {
        $rules = [
            'customer_id' => 'required|numeric|min:1',
            'project_id' => 'required|numeric|min:1',
            'package_id' => 'required|numeric|min:1',
            'payment_mode' => 'required|in:one_time,installment',
            'down_payment' => 'numeric|min:0',
            'installment_months' => 'numeric|min:1',
            'stage' => 'regex:/^MÖW-\\d+$/u',
        ];

        $errors = Validator::make($payload, $rules);
        if (!empty($errors)) {
            return $this->json(['message' => 'Validation failed', 'errors' => $errors], 422);
        }

        return null;
    }

    private function validatePaymentPayload(?array $payload)
    {
        if ($payload === null) {
            return null;
        }

        $rules = [
            'amount' => 'required|numeric|min:0.01',
            'channel' => 'required|in:cash,bank_transfer,sslcommerz,card',
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

    private function preparePaymentPayload(array $payload): ?array
    {
        $payment = $payload['payment'] ?? null;
        if (!is_array($payment)) {
            return null;
        }

        $normalized = [
            'amount' => isset($payment['amount']) ? (float)$payment['amount'] : null,
            'channel' => $payment['channel'] ?? 'cash',
            'reference_no' => $payment['reference_no'] ?? null,
            'bank_name' => $payment['bank_name'] ?? null,
            'gateway' => $payment['gateway'] ?? null,
            'transaction_id' => $payment['transaction_id'] ?? null,
            'note' => $payment['note'] ?? null,
            'received_at' => $payment['received_at'] ?? date('Y-m-d H:i:s'),
        ];

        if ($normalized['amount'] === null || $normalized['amount'] <= 0) {
            return null;
        }

        return $normalized;
    }

    private function authenticatedCustomerId(): ?int
    {
        $user = Auth::user();
        if (!$user || empty($user['customer_id'])) {
            return null;
        }

        return (int)$user['customer_id'];
    }

    private function generateInvoiceNo(?string $projectCode): string
    {
        $prefix = $projectCode ? strtoupper($projectCode) : 'HRM';
        return sprintf('%s-%s', $prefix, date('YmdHis'));
    }

    private function generateReceiptNo(?string $projectCode): string
    {
        $prefix = $projectCode ? strtoupper($projectCode) : 'HRM';

        return sprintf('%s-RCPT-%s-%s', $prefix, date('YmdHis'), random_int(100, 999));
    }

    private function recordSalePayment(array $sale, array $share, array $paymentPayload): array
    {
        $pdo = Database::connection();
        $pdo->beginTransaction();

        try {
            $project = Project::find((int)$sale['project_id']);
            $receiptNo = $this->generateReceiptNo($project['project_code'] ?? null);
            $paymentRecord = ShareSalePayment::create([
                'share_sale_id' => (int)$sale['id'],
                'customer_id' => (int)$sale['customer_id'],
                'amount' => (float)$paymentPayload['amount'],
                'payment_channel' => $paymentPayload['channel'],
                'receipt_no' => $receiptNo,
                'reference_no' => $paymentPayload['reference_no'] ?? null,
                'bank_name' => $paymentPayload['bank_name'] ?? null,
                'gateway' => $paymentPayload['gateway'] ?? null,
                'transaction_id' => $paymentPayload['transaction_id'] ?? null,
                'metadata' => json_encode(['note' => $paymentPayload['note'] ?? null]),
                'received_at' => $paymentPayload['received_at'],
            ]);

            Transaction::create([
                'share_id' => (int)$share['id'],
                'amount' => (float)$paymentPayload['amount'],
                'payment_type' => $paymentPayload['channel'],
                'date' => $paymentPayload['received_at'],
            ]);

            $voucher = Voucher::create([
                'voucher_no' => 'SALE-' . $sale['id'] . '-' . time(),
                'voucher_type' => 'receipt',
                'date' => date('Y-m-d', strtotime($paymentPayload['received_at'])),
                'description' => 'Share sale payment via ' . $paymentPayload['channel'],
                'created_by' => Auth::user()['id'] ?? null,
                'status' => 'approved',
            ]);

            VoucherLine::create([
                'voucher_id' => $voucher['id'],
                'account_id' => 1,
                'debit' => (float)$paymentPayload['amount'],
                'credit' => 0,
                'description' => 'Payment received',
            ]);
            VoucherLine::create([
                'voucher_id' => $voucher['id'],
                'account_id' => 2,
                'debit' => 0,
                'credit' => (float)$paymentPayload['amount'],
                'description' => 'Share sale income',
            ]);

            $pdo->commit();
        } catch (Throwable $exception) {
            $pdo->rollBack();
            throw $exception;
        }

        $receiptData = $this->documentService->getReceiptData($receiptNo);
        $pdfPath = $receiptData ? $this->documentService->generateReceiptPdf($receiptData) : null;

        $customer = Customer::find((int)$sale['customer_id']);
        $this->notificationService->sendReceiptNotification(
            $customer ?? [],
            $receiptNo,
            $receiptData['receipt_url'] ?? $this->buildReceiptUrl($receiptNo)
        );

        return array_merge($paymentRecord, ['voucher_id' => $voucher['id'], 'pdf_path' => $pdfPath]);
    }

    private function buildReceiptUrl(string $receiptNo): string
    {
        $base = rtrim(config('app.url', Env::get('APP_URL', 'http://localhost')), '/');

        return $base . '/receipt/' . $receiptNo;
    }
}
