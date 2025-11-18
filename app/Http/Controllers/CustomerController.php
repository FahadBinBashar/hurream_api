<?php

namespace App\Http\Controllers;

use App\Core\Database;
use App\Core\Request;
use App\Models\Customer;
use App\Support\AuditLogger;
use App\Support\Auth;
use App\Support\DocumentUpload;
use App\Support\Validator;
use DateTime;
use DateTimeInterface;
use InvalidArgumentException;

use function array_flip;
use function array_intersect_key;
use function array_merge;
use function in_array;
use function is_string;
use function preg_match;

class CustomerController extends Controller
{
    public function index(): array
    {
        return ['data' => Customer::all()];
    }

    private const STATUSES = ['new', 'verified', 'blacklisted'];

    public function store(Request $request)
    {
        if ($response = $this->validate($request, [
            'name' => 'required',
            'NID' => 'required|regex:/^\\d{10,17}$/|unique:customers,NID',
            'phone' => 'required|regex:/^01[3-9]\\d{8}$/',
            'address' => 'required',
            'email' => 'email',
            'status' => 'in:' . implode(',', self::STATUSES),
        ])) {
            return $response;
        }

        try {
            $payload = $this->preparePayload($request->all());
        } catch (InvalidArgumentException $exception) {
            return $this->json(['message' => $exception->getMessage()], 422);
        }
        try {
            if ($filePayload = $this->handleDocuments($request)) {
                $payload = array_merge($payload, $filePayload);
            }
        } catch (\Throwable $exception) {
            return $this->json(['message' => $exception->getMessage()], 422);
        }
        $payload['status'] = $payload['status'] ?? 'new';
        if (($payload['status'] ?? null) === 'verified' && empty($payload['verified_at'])) {
            $payload['verified_at'] = date('Y-m-d H:i:s');
        }

        $payload = $this->ensureInvestorMeta($payload);

        $customer = Customer::create($payload);
        AuditLogger::log(Auth::user(), 'create', 'customers', 'customer', (int)$customer['id'], $payload, $request->ip(), $request->userAgent());
        return $this->json(['data' => $customer], 201);
    }

    public function show(Request $request, array $params)
    {
        $customer = Customer::find((int)$params['id']);
        if (!$customer) {
            return $this->json(['message' => 'Customer not found'], 404);
        }

        return $this->json(['data' => $customer]);
    }

    public function update(Request $request, array $params)
    {
        $customer = Customer::find((int)$params['id']);
        if (!$customer) {
            return $this->json(['message' => 'Customer not found'], 404);
        }

        $rules = [
            'name' => 'required',
            'NID' => 'required|regex:/^\\d{10,17}$/|unique:customers,NID,' . $customer['id'],
            'phone' => 'required|regex:/^01[3-9]\\d{8}$/',
            'address' => 'required',
            'email' => 'email',
            'status' => 'in:' . implode(',', self::STATUSES),
        ];

        $merged = array_merge($customer, $request->all());
        $errors = Validator::make($merged, $rules);
        if (!empty($errors)) {
            return $this->json(['message' => 'Validation failed', 'errors' => $errors], 422);
        }

        try {
            $payload = $this->preparePayload($merged);
        } catch (InvalidArgumentException $exception) {
            return $this->json(['message' => $exception->getMessage()], 422);
        }
        try {
            if ($filePayload = $this->handleDocuments($request)) {
                $payload = array_merge($payload, $filePayload);
            }
        } catch (\Throwable $exception) {
            return $this->json(['message' => $exception->getMessage()], 422);
        }
        if (($payload['status'] ?? null) === 'verified' && empty($payload['verified_at'])) {
            $payload['verified_at'] = date('Y-m-d H:i:s');
        }
        if (($payload['status'] ?? null) !== 'verified') {
            $payload['verified_at'] = $payload['verified_at'] ?? null;
        }

        $payload = $this->ensureInvestorMeta($payload, true);

        $updated = Customer::update((int)$params['id'], $payload);
        AuditLogger::log(Auth::user(), 'update', 'customers', 'customer', (int)$params['id'], $payload, $request->ip(), $request->userAgent());
        return $this->json(['data' => $updated]);
    }

    public function destroy(Request $request, array $params)
    {
        $deleted = Customer::delete((int)$params['id']);
        if (!$deleted) {
            return $this->json(['message' => 'Customer not found'], 404);
        }

        AuditLogger::log(Auth::user(), 'delete', 'customers', 'customer', (int)$params['id'], [], $request->ip(), $request->userAgent());
        return $this->json(['message' => 'Customer deleted']);
    }

    private const DATETIME_FIELDS = ['verified_at', 'police_verified_at', 'otp_verified_at', 'admin_approved_at'];

    private function preparePayload(array $input): array
    {
        $allowed = [
            'name',
            'NID',
            'address',
            'phone',
            'email',
            'reference',
            'status',
            'nid_document_path',
            'photo_path',
            'verified_at',
            'police_verification_path',
            'police_verified_at',
            'membership_type',
            'is_investor',
            'investor_no',
            'created_by_employee_id',
            'bank_info',
            'nominee',
            'otp_verified_at',
            'admin_approved_at',
        ];
        $filtered = array_intersect_key($input, array_flip($allowed));

        foreach ($filtered as $key => $value) {
            if (is_string($value)) {
                $filtered[$key] = trim($value);
            }
        }

        foreach (self::DATETIME_FIELDS as $field) {
            if (array_key_exists($field, $filtered)) {
                $filtered[$field] = $this->normalizeDateTime($filtered[$field], $field);
            }
        }

        if (isset($filtered['status']) && !in_array($filtered['status'], self::STATUSES, true)) {
            unset($filtered['status']);
        }

        if (array_key_exists('is_investor', $filtered)) {
            $filtered['is_investor'] = (int)!empty($filtered['is_investor']);
        }

        if (array_key_exists('created_by_employee_id', $filtered)) {
            $filtered['created_by_employee_id'] = $filtered['created_by_employee_id'] !== null
                ? (int)$filtered['created_by_employee_id']
                : null;
        }

        return $filtered;
    }

    private function ensureInvestorMeta(array $payload, bool $isUpdate = false): array
    {
        $payload['membership_type'] = $payload['membership_type'] ?? 'general';
        $payload['is_investor'] = (int)($payload['is_investor'] ?? 1);

        if ($payload['is_investor']) {
            if (empty($payload['investor_no'])) {
                $payload['investor_no'] = $this->generateInvestorNo();
            }
        } else {
            $payload['investor_no'] = $payload['investor_no'] ?? null;
        }

        if (!$isUpdate && empty($payload['created_by_employee_id']) && Auth::user()) {
            $payload['created_by_employee_id'] = Auth::user()['id'] ?? null;
        }

        return $payload;
    }

    private function generateInvestorNo(): string
    {
        $pdo = Database::connection();
        $prefix = 'INV-' . date('Y');
        $stmt = $pdo->prepare('SELECT investor_no FROM customers WHERE investor_no LIKE :prefix ORDER BY investor_no DESC LIMIT 1');
        $stmt->execute(['prefix' => $prefix . '%']);
        $last = $stmt->fetchColumn();
        $next = 1;
        if ($last && preg_match('/(\d+)$/', $last, $matches)) {
            $next = (int)$matches[1] + 1;
        }

        return sprintf('%s-%04d', $prefix, $next);
    }

    private function normalizeDateTime($value, string $field): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if ($value instanceof DateTimeInterface) {
            return $value->format('Y-m-d H:i:s');
        }

        if (is_string($value)) {
            try {
                return (new DateTime($value))->format('Y-m-d H:i:s');
            } catch (\Exception $exception) {
                throw new InvalidArgumentException('Invalid datetime value for ' . $field . '.');
            }
        }

        throw new InvalidArgumentException('Invalid datetime value for ' . $field . '.');
    }

    private function handleDocuments(Request $request): array
    {
        $uploader = new DocumentUpload();
        $paths = [];

        $filesMap = [
            'nid_document' => 'nid_document_path',
            'photo' => 'photo_path',
            'police_verification' => 'police_verification_path',
        ];

        foreach ($filesMap as $field => $column) {
            if ($request->hasFile($field)) {
                try {
                    $paths[$column] = $uploader->store($request->file($field), 'customers');
                } catch (\Throwable $exception) {
                    throw new \RuntimeException('Failed to upload ' . $field . ': ' . $exception->getMessage());
                }
            }
        }

        return $paths;
    }

    public function verifyDocuments(Request $request, array $params)
    {
        $customer = Customer::find((int)$params['id']);
        if (!$customer) {
            return $this->json(['message' => 'Customer not found'], 404);
        }

        $payload = [];
        if ($request->input('nid_verified')) {
            $payload['nid_verified_at'] = date('Y-m-d H:i:s');
        }
        if ($request->input('police_verified')) {
            $payload['police_verified_at'] = date('Y-m-d H:i:s');
        }

        if (empty($payload)) {
            return $this->json(['message' => 'No verification flags provided'], 400);
        }

        $updated = Customer::update((int)$customer['id'], $payload);
        AuditLogger::log(Auth::user(), 'verify_documents', 'customers', 'customer', (int)$customer['id'], $payload, $request->ip(), $request->userAgent());

        return $this->json(['data' => $updated]);
    }
}
