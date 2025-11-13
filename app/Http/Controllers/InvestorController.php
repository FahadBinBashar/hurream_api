<?php

namespace App\Http\Controllers;

use App\Core\Database;
use App\Core\Request;
use App\Models\Investor;
use App\Models\Share;
use App\Support\AuditLogger;
use App\Support\Auth;
use App\Support\DocumentUpload;
use App\Support\Validator;

use function array_flip;
use function array_intersect_key;
use function array_merge;
use function is_string;

class InvestorController extends Controller
{
    public function index(): array
    {
        $investors = Investor::all();
        $pdo = Database::connection();
        foreach ($investors as &$investor) {
            $stmt = $pdo->prepare('SELECT * FROM shares WHERE investor_id = :id');
            $stmt->execute(['id' => $investor['id']]);
            $investor['shares'] = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        }

        return ['data' => $investors];
    }

    private const STATUSES = ['pending', 'active', 'suspended'];

    public function store(Request $request)
    {
        if ($response = $this->validate($request, [
            'name' => 'required',
            'NID' => 'required|regex:/^\\d{10,17}$/|unique:investors,NID',
            'phone' => 'required|regex:/^01[3-9]\\d{8}$/',
            'email' => 'required|email',
            'address' => 'required',
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
        $payload['status'] = $payload['status'] ?? 'pending';
        $payload['otp_verified_at'] = $payload['otp_verified_at'] ?? null;
        $payload['admin_approved_at'] = $payload['admin_approved_at'] ?? null;

        $investor = Investor::create($payload);
        $investor['shares'] = [];
        AuditLogger::log(Auth::user(), 'create', 'investors', 'investor', (int)$investor['id'], $payload, $request->ip(), $request->userAgent());
        return $this->json(['data' => $investor], 201);
    }

    public function show(Request $request, array $params)
    {
        $investor = Investor::find((int)$params['id']);
        if (!$investor) {
            return $this->json(['message' => 'Investor not found'], 404);
        }

        $pdo = Database::connection();
        $stmt = $pdo->prepare('SELECT * FROM shares WHERE investor_id = :id');
        $stmt->execute(['id' => $investor['id']]);
        $investor['shares'] = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        return $this->json(['data' => $investor]);
    }

    public function update(Request $request, array $params)
    {
        $investor = Investor::find((int)$params['id']);
        if (!$investor) {
            return $this->json(['message' => 'Investor not found'], 404);
        }

        $rules = [
            'name' => 'required',
            'NID' => 'required|regex:/^\\d{10,17}$/|unique:investors,NID,' . $investor['id'],
            'phone' => 'required|regex:/^01[3-9]\\d{8}$/',
            'email' => 'required|email',
            'address' => 'required',
            'status' => 'in:' . implode(',', self::STATUSES),
        ];

        $merged = array_merge($investor, $request->all());
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
        $payload['status'] = $payload['status'] ?? 'pending';

        $updated = Investor::update((int)$params['id'], $payload);
        AuditLogger::log(Auth::user(), 'update', 'investors', 'investor', (int)$params['id'], $payload, $request->ip(), $request->userAgent());
        return $this->json(['data' => $updated]);
    }

    public function destroy(Request $request, array $params)
    {
        $deleted = Investor::delete((int)$params['id']);
        if (!$deleted) {
            return $this->json(['message' => 'Investor not found'], 404);
        }

        AuditLogger::log(Auth::user(), 'delete', 'investors', 'investor', (int)$params['id'], [], $request->ip(), $request->userAgent());
        return $this->json(['message' => 'Investor deleted']);
    }

    public function verifyDocuments(Request $request, array $params)
    {
        $investor = Investor::find((int)$params['id']);
        if (!$investor) {
            return $this->json(['message' => 'Investor not found'], 404);
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

        $updated = Investor::update((int)$investor['id'], $payload);
        AuditLogger::log(Auth::user(), 'verify_documents', 'investors', 'investor', (int)$investor['id'], $payload, $request->ip(), $request->userAgent());

        return $this->json(['data' => $updated]);
    }

    private function preparePayload(array $input): array
    {
        $allowed = [
            'name',
            'NID',
            'phone',
            'email',
            'address',
            'bank_info',
            'nominee',
            'status',
            'otp_verified_at',
            'admin_approved_at',
            'nid_document_path',
            'police_verification_path',
            'nid_verified_at',
            'police_verified_at',
        ];

        $filtered = array_intersect_key($input, array_flip($allowed));
        foreach ($filtered as $key => $value) {
            if (is_string($value)) {
                $filtered[$key] = trim($value);
            }
        }

        return $filtered;
    }

    private function handleDocuments(Request $request): array
    {
        $uploader = new DocumentUpload();
        $paths = [];
        $files = [
            'nid_document' => 'nid_document_path',
            'police_verification' => 'police_verification_path',
        ];

        foreach ($files as $field => $column) {
            if ($request->hasFile($field)) {
                $paths[$column] = $uploader->store($request->file($field), 'investors');
            }
        }

        return $paths;
    }
}
