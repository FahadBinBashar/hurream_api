<?php

namespace App\Http\Controllers;

use App\Core\Request;
use App\Models\Customer;
use App\Support\AuditLogger;
use App\Support\Auth;
use App\Support\DocumentUpload;
use App\Support\Validator;

use function array_flip;
use function array_intersect_key;
use function array_merge;
use function in_array;
use function is_string;

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

        $payload = $this->preparePayload($request->all());
        try {
            if ($filePayload = $this->handleDocuments($request)) {
                $payload = array_merge($payload, $filePayload);
            }
        } catch (\Throwable $exception) {
            return $this->json(['message' => $exception->getMessage()], 422);
        }
        $payload['status'] = $payload['status'] ?? 'new';
        if (($payload['status'] ?? null) === 'verified' && empty($payload['verified_at'])) {
            $payload['verified_at'] = date('c');
        }

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

        $payload = $this->preparePayload($merged);
        try {
            if ($filePayload = $this->handleDocuments($request)) {
                $payload = array_merge($payload, $filePayload);
            }
        } catch (\Throwable $exception) {
            return $this->json(['message' => $exception->getMessage()], 422);
        }
        if (($payload['status'] ?? null) === 'verified' && empty($payload['verified_at'])) {
            $payload['verified_at'] = date('c');
        }
        if (($payload['status'] ?? null) !== 'verified') {
            $payload['verified_at'] = $payload['verified_at'] ?? null;
        }

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

    private function preparePayload(array $input): array
    {
        $allowed = ['name', 'NID', 'address', 'phone', 'email', 'reference', 'status', 'nid_document_path', 'photo_path', 'verified_at', 'police_verification_path', 'police_verified_at'];
        $filtered = array_intersect_key($input, array_flip($allowed));

        foreach ($filtered as $key => $value) {
            if (is_string($value)) {
                $filtered[$key] = trim($value);
            }
        }

        if (isset($filtered['status']) && !in_array($filtered['status'], self::STATUSES, true)) {
            unset($filtered['status']);
        }

        return $filtered;
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
}
