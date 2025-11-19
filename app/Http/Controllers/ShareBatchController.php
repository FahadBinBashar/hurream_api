<?php

namespace App\Http\Controllers;

use App\Core\Database;
use App\Core\Request;
use App\Models\Project;
use App\Models\ShareBatch;
use App\Support\AuditLogger;
use App\Support\Auth;
use App\Support\Validator;
use PDO;

class ShareBatchController extends Controller
{
    public function index(Request $request)
    {
        $pdo = Database::connection();
        $conditions = [];
        $params = [];
        if ($projectId = $request->input('project_id')) {
            $conditions[] = 'project_id = :project_id';
            $params['project_id'] = $projectId;
        }
        if ($status = $request->input('status')) {
            $conditions[] = 'status = :status';
            $params['status'] = $status;
        }

        $sql = 'SELECT * FROM share_batches';
        if ($conditions) {
            $sql .= ' WHERE ' . implode(' AND ', $conditions);
        }
        $sql .= ' ORDER BY certificate_start_no';

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        return $this->json(['data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
    }

    public function store(Request $request)
    {
        if ($response = $this->ensureRole(['admin'])) {
            return $response;
        }

        if ($response = $this->validateBatch($request->all())) {
            return $response;
        }

        $payload = $this->payloadFromRequest($request->all());
        $batch = ShareBatch::create($payload);

        AuditLogger::log(Auth::user(), 'create', 'share_batches', 'share_batch', (int)$batch['id'], $payload, $request->ip(), $request->userAgent());

        return $this->json(['data' => $batch], 201);
    }

    public function update(Request $request, array $params)
    {
        if ($response = $this->ensureRole(['admin'])) {
            return $response;
        }

        $batch = ShareBatch::find((int)$params['id']);
        if (!$batch) {
            return $this->json(['message' => 'Batch not found'], 404);
        }

        if ($response = $this->validateBatch($request->all(), (int)$batch['project_id'])) {
            return $response;
        }

        $payload = $this->payloadFromRequest(array_merge($batch, $request->all()));
        $updated = ShareBatch::update((int)$batch['id'], $payload);

        AuditLogger::log(Auth::user(), 'update', 'share_batches', 'share_batch', (int)$batch['id'], $payload, $request->ip(), $request->userAgent());

        return $this->json(['data' => $updated]);
    }

    public function destroy(Request $request, array $params)
    {
        if ($response = $this->ensureRole(['admin'])) {
            return $response;
        }

        $deleted = ShareBatch::delete((int)$params['id']);
        if (!$deleted) {
            return $this->json(['message' => 'Batch not found'], 404);
        }

        AuditLogger::log(Auth::user(), 'delete', 'share_batches', 'share_batch', (int)$params['id'], [], $request->ip(), $request->userAgent());

        return $this->json(['message' => 'Batch deleted']);
    }

    private function validateBatch(array $input, ?int $projectId = null)
    {
        $rules = [
            'project_id' => 'required|numeric|min:1',
            'batch_name' => 'required',
            'share_price' => 'required|numeric|min:1',
            'total_shares' => 'required|numeric|min:1',
            'certificate_start_no' => 'required|numeric|min:1',
            'certificate_end_no' => 'required|numeric|min:1',
            'status' => 'in:active,inactive',
        ];

        $errors = Validator::make($input, $rules);
        if (!empty($errors)) {
            return $this->json(['message' => 'Validation failed', 'errors' => $errors], 422);
        }

        $project = Project::find((int)($input['project_id'] ?? $projectId));
        if (!$project) {
            return $this->json(['message' => 'Project not found'], 404);
        }

        if ((int)$input['certificate_end_no'] < (int)$input['certificate_start_no']) {
            return $this->json(['message' => 'Certificate end cannot be smaller than start'], 422);
        }

        return null;
    }

    private function payloadFromRequest(array $input): array
    {
        $total = (int)$input['total_shares'];
        $available = isset($input['available_shares']) ? (int)$input['available_shares'] : $total;
        $available = min($available, $total);

        return [
            'project_id' => (int)$input['project_id'],
            'batch_name' => trim((string)$input['batch_name']),
            'share_price' => (float)$input['share_price'],
            'total_shares' => $total,
            'available_shares' => $available,
            'certificate_start_no' => (int)$input['certificate_start_no'],
            'certificate_end_no' => (int)$input['certificate_end_no'],
            'status' => $input['status'] ?? 'active',
        ];
    }
}
