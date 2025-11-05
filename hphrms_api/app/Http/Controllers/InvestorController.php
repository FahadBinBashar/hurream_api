<?php

namespace App\Http\Controllers;

use App\Core\Database;
use App\Core\Request;
use App\Models\Investor;
use App\Models\Share;
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
        $payload['status'] = $payload['status'] ?? 'pending';
        $payload['otp_verified_at'] = $payload['otp_verified_at'] ?? null;
        $payload['admin_approved_at'] = $payload['admin_approved_at'] ?? null;

        $investor = Investor::create($payload);
        $investor['shares'] = [];
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
        $payload['status'] = $payload['status'] ?? 'pending';

        $updated = Investor::update((int)$params['id'], $payload);
        return $this->json(['data' => $updated]);
    }

    public function destroy(Request $request, array $params)
    {
        $deleted = Investor::delete((int)$params['id']);
        if (!$deleted) {
            return $this->json(['message' => 'Investor not found'], 404);
        }

        return $this->json(['message' => 'Investor deleted']);
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
        ];

        $filtered = array_intersect_key($input, array_flip($allowed));
        foreach ($filtered as $key => $value) {
            if (is_string($value)) {
                $filtered[$key] = trim($value);
            }
        }

        return $filtered;
    }
}
