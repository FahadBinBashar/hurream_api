<?php

namespace App\Http\Controllers;

use App\Core\Database;
use App\Core\Request;
use App\Models\Project;
use App\Support\AuditLogger;
use App\Support\Auth;
use App\Support\Validator;
use PDO;

class ProjectController extends Controller
{
    public function index(Request $request)
    {
        $pdo = Database::connection();
        $conditions = [];
        $params = [];
        if ($status = $request->input('status')) {
            $conditions[] = 'status = :status';
            $params['status'] = $status;
        }

        $sql = 'SELECT * FROM projects';
        if ($conditions) {
            $sql .= ' WHERE ' . implode(' AND ', $conditions);
        }
        $sql .= ' ORDER BY project_name';

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        return $this->json(['data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
    }

    public function store(Request $request)
    {
        if ($response = $this->ensureRole(['admin'])) {
            return $response;
        }

        if ($response = $this->validateProject($request->all())) {
            return $response;
        }

        $payload = $this->payloadFromRequest($request->all());
        $project = Project::create($payload);

        AuditLogger::log(Auth::user(), 'create', 'projects', 'project', (int)$project['id'], $payload, $request->ip(), $request->userAgent());

        return $this->json(['data' => $project], 201);
    }

    public function show(Request $request, array $params)
    {
        $project = Project::find((int)$params['id']);
        if (!$project) {
            return $this->json(['message' => 'Project not found'], 404);
        }

        return $this->json(['data' => $project]);
    }

    public function update(Request $request, array $params)
    {
        if ($response = $this->ensureRole(['admin'])) {
            return $response;
        }

        $project = Project::find((int)$params['id']);
        if (!$project) {
            return $this->json(['message' => 'Project not found'], 404);
        }

        if ($response = $this->validateProject($request->all(), (int)$project['id'])) {
            return $response;
        }

        $payload = $this->payloadFromRequest(array_merge($project, $request->all()));
        $updated = Project::update((int)$project['id'], $payload);

        AuditLogger::log(Auth::user(), 'update', 'projects', 'project', (int)$project['id'], $payload, $request->ip(), $request->userAgent());

        return $this->json(['data' => $updated]);
    }

    public function destroy(Request $request, array $params)
    {
        if ($response = $this->ensureRole(['admin'])) {
            return $response;
        }

        $deleted = Project::delete((int)$params['id']);
        if (!$deleted) {
            return $this->json(['message' => 'Project not found'], 404);
        }

        AuditLogger::log(Auth::user(), 'delete', 'projects', 'project', (int)$params['id'], [], $request->ip(), $request->userAgent());

        return $this->json(['message' => 'Project removed']);
    }

    private function validateProject(array $input, ?int $ignoreId = null)
    {
        $rules = [
            'project_code' => 'required|alpha_dash|unique:projects,project_code' . ($ignoreId ? ',' . $ignoreId : ''),
            'project_name' => 'required',
            'status' => 'in:active,inactive',
            'certificate_prefix' => 'alpha_dash',
        ];

        $errors = Validator::make($input, $rules);
        if (!empty($errors)) {
            return $this->json(['message' => 'Validation failed', 'errors' => $errors], 422);
        }

        return null;
    }

    private function payloadFromRequest(array $input): array
    {
        return [
            'project_code' => strtoupper(trim((string)$input['project_code'])),
            'project_name' => trim((string)$input['project_name']),
            'location' => $input['location'] ?? null,
            'description' => $input['description'] ?? null,
            'status' => $input['status'] ?? 'active',
            'certificate_prefix' => strtoupper($input['certificate_prefix'] ?? 'HRM'),
        ];
    }
}
