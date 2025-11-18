<?php

namespace App\Http\Controllers;

use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Models\Approval;
use App\Models\Customer;
use App\Models\Share;
use App\Support\AuditLogger;
use App\Support\Auth;
use App\Support\Env;
use App\Support\Validator;

use function array_filter;
use function array_map;
use function array_merge;
use function array_unique;
use function array_values;
use function in_array;
use function is_array;
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
            'unit_price' => 'numeric|min:1',
            'quantity' => 'required|numeric|min:1',
            'payment_mode' => 'required|in:' . implode(',', self::PAYMENT_MODES),
            'stage' => 'required|regex:/^MÖW-\\d+$/u',
            'reinvest_flag' => 'in:0,1',
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
            'unit_price' => 'required|numeric|min:1',
            'quantity' => 'required|numeric|min:1',
            'payment_mode' => 'required|in:' . implode(',', self::PAYMENT_MODES),
            'stage' => 'required|regex:/^MÖW-\\d+$/u',
            'reinvest_flag' => 'in:0,1',
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

        AuditLogger::log(Auth::user(), 'delete', 'shares', 'share', (int)$params['id'], [], $request->ip(), $request->userAgent());
        return $this->json(['message' => 'Share deleted']);
    }

    /**
     * @param array $input
     * @return array{data: array, needs_approval: bool, approvers: array}|Response
     */
    private function prepareShareData(array $input, bool $isUpdate, int $shareId = 0)
    {
        $defaultUnitPrice = (float)Env::get('SHARE_DEFAULT_UNIT_PRICE', 25000);
        $threshold = (float)Env::get('SHARE_MULTI_APPROVAL_THRESHOLD', 500000);

        $unitPrice = isset($input['unit_price']) && $input['unit_price'] !== ''
            ? (float)$input['unit_price']
            : $defaultUnitPrice;
        $quantity = isset($input['quantity']) ? (int)$input['quantity'] : 0;
        $amount = $unitPrice * $quantity;

        $data = [
            'customer_id' => (int)$input['customer_id'],
            'unit_price' => $unitPrice,
            'quantity' => $quantity,
            'amount' => $amount,
            'payment_mode' => $this->normalizePaymentMode($input['payment_mode'] ?? null),
            'stage' => trim((string)($input['stage'] ?? '')),
            'reinvest_flag' => !empty($input['reinvest_flag']) ? 1 : 0,
        ];

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
        ];
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
