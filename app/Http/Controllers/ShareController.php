<?php

namespace App\Http\Controllers;

use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Models\Approval;
use App\Models\Customer;
use App\Models\Installment;
use App\Models\Share;
use App\Models\SharePackage;
use App\Support\AuditLogger;
use App\Support\Auth;
use App\Support\Env;
use App\Support\Validator;
use PDO;

use DateTime;
use function array_filter;
use function array_map;
use function array_merge;
use function array_unique;
use function array_values;
use function in_array;
use function is_array;
use function json_encode;
use function max;
use function strtolower;
use function trim;

class ShareController extends Controller
{
    private const PAYMENT_MODES = ['one_time', 'installment'];
    private const APPROVAL_STATUSES = ['pending', 'approved', 'rejected'];

    public function index(): array
    {
        return ['data' => Share::all()];
    }

    public function store(Request $request)
    {
        if ($response = $this->validate($request, [
            'customer_id' => 'required|numeric|min:1',
            'share_type' => 'in:single,package',
            'package_id' => 'numeric|min:1',
            'unit_price' => 'numeric|min:1',
            'quantity' => 'numeric|min:1',
            'bonus_units' => 'numeric|min:0',
            'payment_mode' => 'required|in:' . implode(',', self::PAYMENT_MODES),
            'stage' => 'required|regex:/^MÖW-\\d+$/u',
            'reinvest_flag' => 'in:0,1',
            'down_payment' => 'numeric|min:0',
            'installment_months' => 'numeric|min:1',
        ])) {
            return $response;
        }

        $payload = $this->prepareShareData($request->all(), false);
        if ($payload instanceof Response) {
            return $payload;
        }

        $customerCheck = $this->resolveInvestorCustomer((int)$payload['data']['customer_id']);
        if ($customerCheck instanceof Response) {
            return $customerCheck;
        }

        $share = Share::create($payload['data']);
        $this->syncInstallments((int)$share['id'], $payload['installments']);

        if ($payload['needs_approval']) {
            $this->syncApprovals((int)$share['id'], $payload['approvers']);
        }

        AuditLogger::log(Auth::user(), 'create', 'shares', 'share', (int)$share['id'], $payload['data'], $request->ip(), $request->userAgent());
        return $this->json(['data' => $share], 201);
    }

    public function show(Request $request, array $params)
    {
        $share = Share::find((int)$params['id']);
        if (!$share) {
            return $this->json(['message' => 'Share not found'], 404);
        }

        return $this->json(['data' => $share]);
    }

    public function update(Request $request, array $params)
    {
        $share = Share::find((int)$params['id']);
        if (!$share) {
            return $this->json(['message' => 'Share not found'], 404);
        }

        $merged = array_merge($share, $request->all());
        $rules = [
            'customer_id' => 'required|numeric|min:1',
            'share_type' => 'in:single,package',
            'package_id' => 'numeric|min:1',
            'unit_price' => 'numeric|min:1',
            'quantity' => 'numeric|min:1',
            'bonus_units' => 'numeric|min:0',
            'payment_mode' => 'required|in:' . implode(',', self::PAYMENT_MODES),
            'stage' => 'required|regex:/^MÖW-\\d+$/u',
            'reinvest_flag' => 'in:0,1',
            'down_payment' => 'numeric|min:0',
            'installment_months' => 'numeric|min:1',
            'approval_status' => 'in:' . implode(',', self::APPROVAL_STATUSES),
        ];

        $errors = Validator::make($merged, $rules);
        if (!empty($errors)) {
            return $this->json(['message' => 'Validation failed', 'errors' => $errors], 422);
        }

        $payload = $this->prepareShareData($merged, true, (int)$share['id']);
        if ($payload instanceof Response) {
            return $payload;
        }

        $customerCheck = $this->resolveInvestorCustomer((int)$payload['data']['customer_id']);
        if ($customerCheck instanceof Response) {
            return $customerCheck;
        }

        $updated = Share::update((int)$params['id'], $payload['data']);
        $this->syncInstallments((int)$share['id'], $payload['installments']);

        if ($payload['needs_approval']) {
            if (!empty($payload['approvers'])) {
                $this->syncApprovals((int)$share['id'], $payload['approvers']);
            }
        } else {
            $this->clearApprovals((int)$share['id']);
        }

        AuditLogger::log(Auth::user(), 'update', 'shares', 'share', (int)$params['id'], $payload['data'], $request->ip(), $request->userAgent());
        return $this->json(['data' => $updated]);
    }

    public function destroy(Request $request, array $params)
    {
        $deleted = Share::delete((int)$params['id']);
        if (!$deleted) {
            return $this->json(['message' => 'Share not found'], 404);
        }

        $this->clearApprovals((int)$params['id']);
        $this->syncInstallments((int)$params['id'], []);

        AuditLogger::log(Auth::user(), 'delete', 'shares', 'share', (int)$params['id'], [], $request->ip(), $request->userAgent());
        return $this->json(['message' => 'Share deleted']);
    }

    /**
     * @param array $input
     * @return array{data: array, needs_approval: bool, approvers: array, installments: array}|Response
     */
    private function prepareShareData(array $input, bool $isUpdate, int $shareId = 0)
    {
        $defaultUnitPrice = (float)Env::get('SHARE_DEFAULT_UNIT_PRICE', 25000);
        $threshold = (float)Env::get('SHARE_MULTI_APPROVAL_THRESHOLD', 500000);

        $shareType = $this->normalizeShareType($input['share_type'] ?? 'single');
        $data = [
            'customer_id' => (int)$input['customer_id'],
            'share_type' => $shareType,
            'payment_mode' => $this->normalizePaymentMode($input['payment_mode'] ?? null),
            'stage' => trim((string)($input['stage'] ?? '')),
            'reinvest_flag' => !empty($input['reinvest_flag']) ? 1 : 0,
            'status' => $input['status'] ?? 'active',
        ];

        $amount = 0.0;
        $schedule = [];

        if ($shareType === 'package') {
            $packageId = (int)($input['package_id'] ?? 0);
            if ($packageId <= 0) {
                return $this->json([
                    'message' => 'Validation failed',
                    'errors' => ['package_id' => ['Package selection is required for package issues.']],
                ], 422);
            }

            $package = SharePackage::find($packageId);
            if (!$package) {
                return $this->json(['message' => 'Package not found'], 422);
            }
            if (($package['status'] ?? 'active') !== 'active') {
                return $this->json(['message' => 'Package is inactive'], 422);
            }

            $data['package_id'] = $packageId;
            $data['unit_price'] = $defaultUnitPrice;
            $data['share_units'] = (int)$package['auto_share_units'];
            $data['bonus_units'] = (int)$package['bonus_share_units'];
            $data['total_units'] = $data['share_units'] + $data['bonus_units'];
            $amount = (float)$package['package_price'];
            $data['amount'] = $amount;
            $data['down_payment'] = isset($input['down_payment'])
                ? (float)$input['down_payment']
                : (float)($package['down_payment'] ?? 0);
            $data['installment_months'] = isset($input['installment_months'])
                ? (int)$input['installment_months']
                : (int)($package['duration_months'] ?? 0);
            $data['monthly_installment'] = isset($input['monthly_installment'])
                ? (float)$input['monthly_installment']
                : (float)($package['monthly_installment'] ?? 0);
            $data['benefits_snapshot'] = json_encode($this->packageSnapshot($package), JSON_UNESCAPED_UNICODE);
        } else {
            $quantity = isset($input['quantity']) ? (int)$input['quantity'] : 0;
            if ($quantity <= 0) {
                return $this->json([
                    'message' => 'Validation failed',
                    'errors' => ['quantity' => ['Quantity must be greater than zero.']],
                ], 422);
            }

            $unitPrice = isset($input['unit_price']) && $input['unit_price'] !== ''
                ? (float)$input['unit_price']
                : $defaultUnitPrice;
            $data['unit_price'] = $unitPrice;
            $data['share_units'] = $quantity;
            $data['bonus_units'] = isset($input['bonus_units']) ? max(0, (int)$input['bonus_units']) : 0;
            $data['total_units'] = $data['share_units'] + $data['bonus_units'];
            $amount = $unitPrice * $data['share_units'];
            $data['amount'] = $amount;
            $data['down_payment'] = isset($input['down_payment'])
                ? (float)$input['down_payment']
                : ($data['payment_mode'] === 'one_time' ? $amount : 0);
            $data['installment_months'] = isset($input['installment_months'])
                ? (int)$input['installment_months']
                : null;
            $data['monthly_installment'] = isset($input['monthly_installment'])
                ? (float)$input['monthly_installment']
                : null;
            $data['package_id'] = null;
            $data['benefits_snapshot'] = null;
        }

        if ($data['total_units'] <= 0) {
            return $this->json(['message' => 'Validation failed', 'errors' => ['total_units' => ['Share units must be greater than zero.']]], 422);
        }

        if ($data['down_payment'] < 0) {
            return $this->json(['message' => 'Validation failed', 'errors' => ['down_payment' => ['Down payment must be positive.']]], 422);
        }

        if ($data['down_payment'] > $amount) {
            return $this->json(['message' => 'Validation failed', 'errors' => ['down_payment' => ['Down payment cannot exceed total amount.']]], 422);
        }

        if ($data['payment_mode'] === 'installment') {
            $months = (int)($data['installment_months'] ?? 0);
            if ($months < 1) {
                return $this->json(['message' => 'Validation failed', 'errors' => ['installment_months' => ['Installment months must be at least 1 for installment sales.']]], 422);
            }
            $remaining = max($amount - $data['down_payment'], 0);
            if ($remaining <= 0) {
                $remaining = $amount;
                $data['down_payment'] = 0;
            }
            $monthlyInstallment = $data['monthly_installment'] ?? round($remaining / $months, 2);
            if ($monthlyInstallment <= 0) {
                return $this->json(['message' => 'Validation failed', 'errors' => ['monthly_installment' => ['Monthly installment must be greater than zero.']]], 422);
            }

            $data['monthly_installment'] = $monthlyInstallment;
            $data['installment_months'] = $months;
            $schedule = $this->buildInstallments($remaining, $months, $monthlyInstallment);
        } else {
            $data['down_payment'] = $data['down_payment'] ?: $amount;
            $data['monthly_installment'] = null;
            $data['installment_months'] = null;
            $schedule = [];
        }

        $needsApproval = $amount >= $threshold;
        $approvers = [];

        if ($needsApproval) {
            $approversInput = $input['approvers'] ?? null;
            if (is_array($approversInput)) {
                $approvers = $this->normalizeApprovers($approversInput);
            }

            if (count($approvers) < 3) {
                $existingCount = $isUpdate ? $this->approvalCount($shareId) : 0;
                if ($existingCount < 3) {
                    return $this->json([
                        'message' => 'Validation failed',
                        'errors' => [
                            'approvers' => ['At least 3 distinct approvers are required for high value share issues.'],
                        ],
                    ], 422);
                }
            }

            $data['approval_status'] = isset($input['approval_status']) && in_array($input['approval_status'], self::APPROVAL_STATUSES, true)
                ? $input['approval_status']
                : 'pending';
            $data['approver_gate_triggered'] = 1;
        } else {
            $data['approval_status'] = isset($input['approval_status']) && in_array($input['approval_status'], self::APPROVAL_STATUSES, true)
                ? $input['approval_status']
                : 'approved';
            $data['approver_gate_triggered'] = 0;
        }

        return [
            'data' => $data,
            'needs_approval' => $needsApproval,
            'approvers' => $approvers,
            'installments' => $schedule,
        ];
    }

    private function normalizeShareType(?string $type): string
    {
        $type = $type ? strtolower(trim($type)) : '';
        return in_array($type, ['package'], true) ? 'package' : 'single';
    }

    private function packageSnapshot(array $package): array
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare('SELECT benefit_type, benefit_value, notes FROM package_benefits WHERE package_id = :id ORDER BY id');
        $stmt->execute(['id' => (int)$package['id']]);

        return [
            'package' => [
                'id' => (int)$package['id'],
                'package_name' => $package['package_name'],
                'package_code' => $package['package_code'],
                'package_price' => (float)$package['package_price'],
                'duration_months' => (int)$package['duration_months'],
                'monthly_installment' => (float)$package['monthly_installment'],
                'bonus_share_percent' => (float)$package['bonus_share_percent'],
                'bonus_share_units' => (int)$package['bonus_share_units'],
                'free_nights' => (int)$package['free_nights'],
                'lifetime_discount' => (float)$package['lifetime_discount'],
                'tour_voucher_value' => (float)$package['tour_voucher_value'],
                'gift_items' => $package['gift_items'],
            ],
            'benefits' => $stmt->fetchAll(PDO::FETCH_ASSOC),
        ];
    }

    private function buildInstallments(float $amount, int $months, float $monthlyAmount): array
    {
        $schedule = [];
        $remaining = round($amount, 2);
        $date = new DateTime('first day of next month');

        for ($i = 1; $i <= $months; $i++) {
            $dueAmount = $i === $months ? $remaining : min($remaining, round($monthlyAmount, 2));
            $schedule[] = [
                'due_date' => $date->format('Y-m-d'),
                'amount' => round($dueAmount, 2),
            ];
            $remaining = round($remaining - $dueAmount, 2);
            $date->modify('+1 month');
        }

        return $schedule;
    }

    private function syncInstallments(int $shareId, array $installments): void
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare('DELETE FROM installments WHERE related_type = :type AND related_id = :id');
        $stmt->execute([
            'type' => 'customer_share',
            'id' => $shareId,
        ]);

        foreach ($installments as $installment) {
            Installment::create([
                'related_type' => 'customer_share',
                'related_id' => $shareId,
                'due_date' => $installment['due_date'],
                'amount' => $installment['amount'],
                'status' => 'pending',
            ]);
        }
    }

    private function resolveInvestorCustomer(int $customerId)
    {
        $customer = Customer::find($customerId);
        if (!$customer) {
            return $this->json(['message' => 'Customer not found for share'], 422);
        }

        if (empty($customer['is_investor'])) {
            return $this->json(['message' => 'Selected customer is not flagged as an investor'], 422);
        }

        return $customer;
    }

    private function normalizePaymentMode(?string $mode): string
    {
        $mode = $mode ? trim($mode) : '';
        if (!in_array($mode, self::PAYMENT_MODES, true)) {
            return self::PAYMENT_MODES[0];
        }

        return $mode;
    }

    private function normalizeApprovers(array $approvers): array
    {
        $filtered = array_filter(array_map('intval', $approvers), static fn($value) => $value > 0);
        return array_values(array_unique($filtered));
    }

    private function syncApprovals(int $shareId, array $approvers): void
    {
        $this->clearApprovals($shareId);
        foreach ($approvers as $approverId) {
            Approval::create([
                'module' => 'share_issue',
                'record_id' => $shareId,
                'approver_id' => $approverId,
                'status' => 'pending',
            ]);
        }
    }

    private function clearApprovals(int $shareId): void
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare('DELETE FROM approvals WHERE module = :module AND record_id = :record_id');
        $stmt->execute([
            'module' => 'share_issue',
            'record_id' => $shareId,
        ]);
    }

    private function approvalCount(int $shareId): int
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM approvals WHERE module = :module AND record_id = :record_id');
        $stmt->execute([
            'module' => 'share_issue',
            'record_id' => $shareId,
        ]);

        return (int)$stmt->fetchColumn();
    }
}
