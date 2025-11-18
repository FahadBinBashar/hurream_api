<?php

namespace App\Http\Controllers;

use App\Core\Database;
use App\Core\Request;
use App\Models\Grade;
use App\Support\AuditLogger;
use App\Support\Auth;
use PDO;

class GradeController extends Controller
{
    private const STATUSES = ['active', 'inactive'];

    public function index(Request $request)
    {
        $pdo = Database::connection();
        $conditions = [];
        $params = [];
        if ($status = $request->input('status')) {
            $conditions[] = 'status = :status';
            $params['status'] = $status;
        }

        $query = 'SELECT * FROM grades';
        if ($conditions) {
            $query .= ' WHERE ' . implode(' AND ', $conditions);
        }
        $query .= ' ORDER BY grade_no ASC';

        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        $grades = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return $this->json([
            'data' => $grades,
            'meta' => [
                'grades' => $this->gradeStats($pdo),
                'designations' => $this->designationStats($pdo),
                'recent_changes' => $this->recentChanges($pdo),
            ],
        ]);
    }

    public function store(Request $request)
    {
        if ($response = $this->ensureRole(['admin'])) {
            return $response;
        }

        if ($response = $this->validate($request, [
            'grade_no' => 'required|numeric|min:1',
            'grade_name' => 'required',
            'description' => '',
            'status' => 'in:' . implode(',', self::STATUSES),
        ])) {
            return $response;
        }

        $payload = $this->filterGradePayload($request->all());
        $payload['grade_no'] = (int)$payload['grade_no'];
        $payload['status'] = $payload['status'] ?? 'active';

        if ($this->gradeNumberExists($payload['grade_no'])) {
            return $this->json(['message' => 'Grade number already exists'], 422);
        }

        $grade = Grade::create($payload);
        AuditLogger::log(Auth::user(), 'create', 'grades', 'grade', (int)$grade['id'], $payload, $request->ip(), $request->userAgent());

        return $this->json(['data' => $grade], 201);
    }

    public function update(Request $request, array $params)
    {
        if ($response = $this->ensureRole(['admin'])) {
            return $response;
        }

        $grade = Grade::find((int)$params['id']);
        if (!$grade) {
            return $this->json(['message' => 'Grade not found'], 404);
        }

        if ($response = $this->validate($request, [
            'grade_no' => 'numeric|min:1',
            'grade_name' => '',
            'description' => '',
            'status' => 'in:' . implode(',', self::STATUSES),
        ])) {
            return $response;
        }

        $updates = $this->filterGradePayload($request->all());
        if (empty($updates)) {
            return $this->json(['data' => $grade]);
        }

        if (isset($updates['grade_no'])) {
            $updates['grade_no'] = (int)$updates['grade_no'];
            if ($this->gradeNumberExists($updates['grade_no'], (int)$grade['id'])) {
                return $this->json(['message' => 'Grade number already exists'], 422);
            }
        }

        $updated = Grade::update((int)$grade['id'], $updates);
        AuditLogger::log(Auth::user(), 'update', 'grades', 'grade', (int)$grade['id'], $updates, $request->ip(), $request->userAgent());

        return $this->json(['data' => $updated]);
    }

    private function filterGradePayload(array $input): array
    {
        $allowed = ['grade_no', 'grade_name', 'description', 'status'];
        $filtered = array_intersect_key($input, array_flip($allowed));
        if (isset($filtered['grade_name'])) {
            $filtered['grade_name'] = trim((string)$filtered['grade_name']);
        }
        if (array_key_exists('description', $filtered)) {
            $filtered['description'] = $filtered['description'] === null ? null : trim((string)$filtered['description']);
        }
        if (isset($filtered['status']) && $filtered['status'] === '') {
            unset($filtered['status']);
        }

        return $filtered;
    }

    private function gradeNumberExists(int $gradeNo, ?int $ignoreId = null): bool
    {
        $pdo = Database::connection();
        $query = 'SELECT COUNT(*) FROM grades WHERE grade_no = :grade_no';
        $params = ['grade_no' => $gradeNo];
        if ($ignoreId) {
            $query .= ' AND id != :id';
            $params['id'] = $ignoreId;
        }

        $stmt = $pdo->prepare($query);
        $stmt->execute($params);

        return (int)$stmt->fetchColumn() > 0;
    }

    private function gradeStats(PDO $pdo): array
    {
        $stmt = $pdo->query(<<<SQL
            SELECT
                COUNT(*) AS total,
                SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) AS active_count,
                SUM(CASE WHEN status = 'inactive' THEN 1 ELSE 0 END) AS inactive_count
            FROM grades
        SQL);
        $stats = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

        return [
            'total' => (int)($stats['total'] ?? 0),
            'active' => (int)($stats['active_count'] ?? 0),
            'inactive' => (int)($stats['inactive_count'] ?? 0),
        ];
    }

    private function designationStats(PDO $pdo): array
    {
        $stmt = $pdo->query(<<<SQL
            SELECT
                COUNT(*) AS total,
                SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) AS active_count,
                SUM(CASE WHEN status = 'inactive' THEN 1 ELSE 0 END) AS inactive_count
            FROM designations
        SQL);
        $stats = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

        return [
            'total' => (int)($stats['total'] ?? 0),
            'active' => (int)($stats['active_count'] ?? 0),
            'inactive' => (int)($stats['inactive_count'] ?? 0),
        ];
    }

    private function recentChanges(PDO $pdo): array
    {
        $gradeStmt = $pdo->query(
            "SELECT id, grade_name AS name, updated_at, created_at FROM grades " .
            "ORDER BY (CASE WHEN updated_at IS NULL THEN 1 ELSE 0 END), updated_at DESC, created_at DESC LIMIT 5"
        );
        $grades = array_map(function ($row) {
            return [
                'type' => 'grade',
                'id' => (int)$row['id'],
                'name' => $row['name'],
                'timestamp' => $row['updated_at'] ?: $row['created_at'],
            ];
        }, $gradeStmt->fetchAll(PDO::FETCH_ASSOC));

        $designationStmt = $pdo->query(
            "SELECT id, designation_name AS name, updated_at, created_at FROM designations " .
            "ORDER BY (CASE WHEN updated_at IS NULL THEN 1 ELSE 0 END), updated_at DESC, created_at DESC LIMIT 5"
        );
        $designations = array_map(function ($row) {
            return [
                'type' => 'designation',
                'id' => (int)$row['id'],
                'name' => $row['name'],
                'timestamp' => $row['updated_at'] ?: $row['created_at'],
            ];
        }, $designationStmt->fetchAll(PDO::FETCH_ASSOC));

        $combined = array_merge($grades, $designations);
        usort($combined, function ($a, $b) {
            return strcmp((string)($b['timestamp'] ?? ''), (string)($a['timestamp'] ?? ''));
        });

        return array_slice($combined, 0, 5);
    }
}
