<?php

namespace App\Http\Controllers;

use App\Core\Database;
use App\Core\Request;
use App\Models\Designation;
use App\Models\Grade;
use App\Support\AuditLogger;
use App\Support\Auth;
use PDO;

class DesignationController extends Controller
{
    private const STATUSES = ['active', 'inactive'];

    public function index(Request $request)
    {
        $pdo = Database::connection();
        $conditions = [];
        $params = [];
        if ($gradeId = $request->input('grade_id')) {
            $conditions[] = 'grade_id = :grade_id';
            $params['grade_id'] = (int)$gradeId;
        }
        if ($status = $request->input('status')) {
            $conditions[] = 'status = :status';
            $params['status'] = $status;
        }

        $query = 'SELECT * FROM designations';
        if ($conditions) {
            $query .= ' WHERE ' . implode(' AND ', $conditions);
        }
        $query .= ' ORDER BY grade_id ASC, designation_name ASC';

        $stmt = $pdo->prepare($query);
        $stmt->execute($params);

        return $this->json(['data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
    }

    public function store(Request $request)
    {
        if ($response = $this->ensureRole(['admin', 'hr'])) {
            return $response;
        }

        if ($response = $this->validate($request, [
            'grade_id' => 'required|numeric|min:1',
            'designation_name' => 'required',
            'description' => '',
            'status' => 'in:' . implode(',', self::STATUSES),
        ])) {
            return $response;
        }

        $payload = $this->filterPayload($request->all());
        $grade = Grade::find((int)$payload['grade_id']);
        if (!$grade) {
            return $this->json(['message' => 'Grade not found'], 422);
        }
        if (isset($grade['status']) && $grade['status'] !== 'active') {
            return $this->json(['message' => 'Grade is inactive'], 422);
        }

        if ($this->designationExists((int)$payload['grade_id'], $payload['designation_name'])) {
            return $this->json(['message' => 'Designation already exists for this grade'], 422);
        }

        $payload['status'] = $payload['status'] ?? 'active';
        $designation = Designation::create($payload);
        AuditLogger::log(Auth::user(), 'create', 'designations', 'designation', (int)$designation['id'], $payload, $request->ip(), $request->userAgent());

        return $this->json(['data' => $designation], 201);
    }

    public function update(Request $request, array $params)
    {
        if ($response = $this->ensureRole(['admin', 'hr'])) {
            return $response;
        }

        $designation = Designation::find((int)$params['id']);
        if (!$designation) {
            return $this->json(['message' => 'Designation not found'], 404);
        }

        if ($response = $this->validate($request, [
            'grade_id' => 'numeric|min:1',
            'designation_name' => '',
            'description' => '',
            'status' => 'in:' . implode(',', self::STATUSES),
        ])) {
            return $response;
        }

        $updates = $this->filterPayload($request->all());
        if (empty($updates)) {
            return $this->json(['data' => $designation]);
        }

        $gradeId = isset($updates['grade_id']) ? (int)$updates['grade_id'] : (int)$designation['grade_id'];
        $grade = Grade::find($gradeId);
        if (!$grade) {
            return $this->json(['message' => 'Grade not found'], 422);
        }
        if (isset($grade['status']) && $grade['status'] !== 'active') {
            return $this->json(['message' => 'Grade is inactive'], 422);
        }

        $name = $updates['designation_name'] ?? $designation['designation_name'];
        if ($this->designationExists($gradeId, $name, (int)$designation['id'])) {
            return $this->json(['message' => 'Designation already exists for this grade'], 422);
        }

        $updates['grade_id'] = $gradeId;
        $updated = Designation::update((int)$designation['id'], $updates);
        AuditLogger::log(Auth::user(), 'update', 'designations', 'designation', (int)$designation['id'], $updates, $request->ip(), $request->userAgent());

        return $this->json(['data' => $updated]);
    }

    public function byGrade(Request $request, array $params)
    {
        $grade = Grade::find((int)$params['id']);
        if (!$grade) {
            return $this->json(['message' => 'Grade not found'], 404);
        }

        $pdo = Database::connection();
        $stmt = $pdo->prepare("SELECT * FROM designations WHERE grade_id = :grade_id AND status = 'active' ORDER BY designation_name ASC");
        $stmt->execute(['grade_id' => (int)$grade['id']]);

        return $this->json([
            'grade' => $grade,
            'data' => $stmt->fetchAll(PDO::FETCH_ASSOC),
        ]);
    }

    private function filterPayload(array $input): array
    {
        $allowed = ['grade_id', 'designation_name', 'description', 'status'];
        $filtered = array_intersect_key($input, array_flip($allowed));
        if (isset($filtered['grade_id'])) {
            $filtered['grade_id'] = (int)$filtered['grade_id'];
        }
        if (isset($filtered['designation_name'])) {
            $filtered['designation_name'] = trim((string)$filtered['designation_name']);
        }
        if (array_key_exists('description', $filtered)) {
            $filtered['description'] = $filtered['description'] === null ? null : trim((string)$filtered['description']);
        }
        if (isset($filtered['status']) && $filtered['status'] === '') {
            unset($filtered['status']);
        }

        return $filtered;
    }

    private function designationExists(int $gradeId, string $name, ?int $ignoreId = null): bool
    {
        $pdo = Database::connection();
        $query = 'SELECT COUNT(*) FROM designations WHERE grade_id = :grade_id AND designation_name = :name';
        $params = ['grade_id' => $gradeId, 'name' => $name];
        if ($ignoreId) {
            $query .= ' AND id != :id';
            $params['id'] = $ignoreId;
        }

        $stmt = $pdo->prepare($query);
        $stmt->execute($params);

        return (int)$stmt->fetchColumn() > 0;
    }
}
