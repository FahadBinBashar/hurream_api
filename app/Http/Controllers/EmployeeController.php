<?php

namespace App\Http\Controllers;

use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Models\AttendanceRecord;
use App\Models\Designation;
use App\Models\Employee;
use App\Models\EmployeeKpi;
use App\Models\EmployeeSalary;
use App\Models\Grade;
use App\Models\LeaveRequest;
use App\Support\AuditLogger;
use App\Support\Auth;
use App\Support\DocumentUpload;
use App\Support\Validator;

use function array_flip;
use function array_intersect_key;
use function array_merge;
use function is_array;
use function is_string;
use function json_encode;

class EmployeeController extends Controller
{
    public function index(): array
    {
        return ['data' => Employee::all()];
    }

    private const STATUSES = ['active', 'probation', 'terminated'];

    public function store(Request $request)
    {
        if ($response = $this->validate($request, [
            'name' => 'required',
            'father_name' => 'required',
            'mother_name' => 'required',
            'nid' => 'required|regex:/^\\d{10,17}$/|unique:employees,nid',
            'address' => 'required',
            'phone' => 'required|regex:/^01[3-9]\\d{8}$/',
            'email' => 'email',
            'education' => 'required',
            'grade_id' => 'required|numeric|min:1',
            'designation_id' => 'required|numeric|min:1',
            'join_date' => 'required|date',
            'salary' => 'required|numeric|min:0',
            'documents' => 'array|min:1',
            'status' => 'in:' . implode(',', self::STATUSES),
        ])) {
            return $response;
        }

        $payload = $this->preparePayload($request->all());
        $gradeResolution = $this->applyGradeSelection($payload);
        if ($gradeResolution instanceof Response) {
            return $gradeResolution;
        }
        $payload = $gradeResolution;
        try {
            if ($filePayload = $this->handleDocuments($request)) {
                $payload = array_merge($payload, $filePayload);
            }
        } catch (\Throwable $exception) {
            return $this->json(['message' => $exception->getMessage()], 422);
        }
        $payload['status'] = $payload['status'] ?? 'active';

        if (isset($payload['documents']) && is_array($payload['documents'])) {
            $payload['document_checklist'] = json_encode($payload['documents']);
            unset($payload['documents']);
        }

        $employee = Employee::create($payload);
        AuditLogger::log(Auth::user(), 'create', 'employees', 'employee', (int)$employee['id'], $payload, $request->ip(), $request->userAgent());
        return $this->json(['data' => $employee], 201);
    }

    public function show(Request $request, array $params)
    {
        $employee = Employee::find((int)$params['id']);
        if (!$employee) {
            return $this->json(['message' => 'Employee not found'], 404);
        }

        return $this->json(['data' => $employee]);
    }

    public function update(Request $request, array $params)
    {
        $employee = Employee::find((int)$params['id']);
        if (!$employee) {
            return $this->json(['message' => 'Employee not found'], 404);
        }

        $rules = [
            'name' => 'required',
            'father_name' => 'required',
            'mother_name' => 'required',
            'nid' => 'required|regex:/^\\d{10,17}$/|unique:employees,nid,' . $employee['id'],
            'address' => 'required',
            'phone' => 'required|regex:/^01[3-9]\\d{8}$/',
            'email' => 'email',
            'education' => 'required',
            'grade_id' => 'required|numeric|min:1',
            'designation_id' => 'required|numeric|min:1',
            'join_date' => 'required|date',
            'salary' => 'required|numeric|min:0',
            'documents' => 'array|min:1',
            'status' => 'in:' . implode(',', self::STATUSES),
        ];

        $merged = array_merge($employee, $request->all());
        $errors = Validator::make($merged, $rules);
        if (!empty($errors)) {
            return $this->json(['message' => 'Validation failed', 'errors' => $errors], 422);
        }

        $payload = $this->preparePayload($merged);
        $gradeResolution = $this->applyGradeSelection($payload);
        if ($gradeResolution instanceof Response) {
            return $gradeResolution;
        }
        $payload = $gradeResolution;
        try {
            if ($filePayload = $this->handleDocuments($request)) {
                $payload = array_merge($payload, $filePayload);
            }
        } catch (\Throwable $exception) {
            return $this->json(['message' => $exception->getMessage()], 422);
        }
        $payload['status'] = $payload['status'] ?? 'active';
        if (isset($payload['documents']) && is_array($payload['documents'])) {
            $payload['document_checklist'] = json_encode($payload['documents']);
            unset($payload['documents']);
        }

        $updated = Employee::update((int)$params['id'], $payload);
        AuditLogger::log(Auth::user(), 'update', 'employees', 'employee', (int)$params['id'], $payload, $request->ip(), $request->userAgent());
        return $this->json(['data' => $updated]);
    }

    public function destroy(Request $request, array $params)
    {
        $deleted = Employee::delete((int)$params['id']);
        if (!$deleted) {
            return $this->json(['message' => 'Employee not found'], 404);
        }

        AuditLogger::log(Auth::user(), 'delete', 'employees', 'employee', (int)$params['id'], [], $request->ip(), $request->userAgent());
        return $this->json(['message' => 'Employee deleted']);
    }

    public function checkIn(Request $request, array $params)
    {
        $employee = Employee::find((int)$params['id']);
        if (!$employee) {
            return $this->json(['message' => 'Employee not found'], 404);
        }

        $data = $request->all();
        $record = AttendanceRecord::create([
            'employee_id' => $employee['id'],
            'check_in_at' => $data['check_in_at'] ?? date('Y-m-d H:i:s'),
            'source' => $data['source'] ?? 'manual',
            'status' => $data['status'] ?? 'present',
        ]);

        AuditLogger::log(Auth::user(), 'attendance_check_in', 'employees', 'attendance', (int)$record['id'], $data, $request->ip(), $request->userAgent());

        return $this->json(['data' => $record], 201);
    }

    public function checkOut(Request $request, array $params)
    {
        $employee = Employee::find((int)$params['id']);
        if (!$employee) {
            return $this->json(['message' => 'Employee not found'], 404);
        }

        $pdo = Database::connection();
        $stmt = $pdo->prepare('SELECT * FROM attendance_records WHERE employee_id = :id AND check_out_at IS NULL ORDER BY id DESC LIMIT 1');
        $stmt->execute(['id' => $employee['id']]);
        $record = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!$record) {
            return $this->json(['message' => 'No active attendance record'], 400);
        }

        $updated = AttendanceRecord::update((int)$record['id'], [
            'check_out_at' => $request->input('check_out_at') ?? date('Y-m-d H:i:s'),
        ]);

        AuditLogger::log(Auth::user(), 'attendance_check_out', 'employees', 'attendance', (int)$record['id'], [], $request->ip(), $request->userAgent());

        return $this->json(['data' => $updated]);
    }

    public function attendance(Request $request, array $params)
    {
        $employee = Employee::find((int)$params['id']);
        if (!$employee) {
            return $this->json(['message' => 'Employee not found'], 404);
        }

        $filters = $request->all();
        $pdo = Database::connection();
        $conditions = ['employee_id = :id'];
        $paramsQuery = ['id' => $employee['id']];
        if (!empty($filters['from'])) {
            $conditions[] = 'check_in_at >= :from';
            $paramsQuery['from'] = $filters['from'];
        }
        if (!empty($filters['to'])) {
            $conditions[] = 'check_in_at <= :to';
            $paramsQuery['to'] = $filters['to'];
        }

        $sql = 'SELECT * FROM attendance_records WHERE ' . implode(' AND ', $conditions) . ' ORDER BY check_in_at DESC';
        $stmt = $pdo->prepare($sql);
        $stmt->execute($paramsQuery);

        return $this->json(['data' => $stmt->fetchAll(\PDO::FETCH_ASSOC)]);
    }

    public function leaveIndex(Request $request, array $params)
    {
        $employee = Employee::find((int)$params['id']);
        if (!$employee) {
            return $this->json(['message' => 'Employee not found'], 404);
        }

        $pdo = Database::connection();
        $stmt = $pdo->prepare('SELECT * FROM leave_requests WHERE employee_id = :id ORDER BY created_at DESC');
        $stmt->execute(['id' => $employee['id']]);

        return $this->json(['data' => $stmt->fetchAll(\PDO::FETCH_ASSOC)]);
    }

    public function leaveStore(Request $request, array $params)
    {
        $employee = Employee::find((int)$params['id']);
        if (!$employee) {
            return $this->json(['message' => 'Employee not found'], 404);
        }

        if ($response = $this->validate($request, [
            'type' => 'required|in:casual,sick,earn',
            'from_date' => 'required|date',
            'to_date' => 'required|date',
            'reason' => 'required',
        ])) {
            return $response;
        }

        $payload = $request->all();
        $payload['employee_id'] = $employee['id'];
        $payload['status'] = $payload['status'] ?? 'pending';
        $leave = LeaveRequest::create($payload);

        AuditLogger::log(Auth::user(), 'create', 'employees', 'leave_request', (int)$leave['id'], $payload, $request->ip(), $request->userAgent());

        return $this->json(['data' => $leave], 201);
    }

    public function leaveUpdate(Request $request, array $params)
    {
        $leave = LeaveRequest::find((int)$params['leave_id']);
        if (!$leave) {
            return $this->json(['message' => 'Leave request not found'], 404);
        }

        if ($response = $this->validate($request, [
            'type' => 'in:casual,sick,earn',
            'from_date' => 'date',
            'to_date' => 'date',
            'status' => 'in:pending,approved,rejected',
        ])) {
            return $response;
        }

        $payload = array_merge($leave, $request->all());
        $updated = LeaveRequest::update((int)$leave['id'], $payload);

        AuditLogger::log(Auth::user(), 'update', 'employees', 'leave_request', (int)$leave['id'], $payload, $request->ip(), $request->userAgent());

        return $this->json(['data' => $updated]);
    }

    public function leaveApprove(Request $request, array $params)
    {
        $leave = LeaveRequest::find((int)$params['leave_id']);
        if (!$leave) {
            return $this->json(['message' => 'Leave request not found'], 404);
        }

        $payload = [
            'status' => $request->input('status') ?? 'approved',
            'approved_by' => Auth::user()['id'] ?? null,
        ];

        $updated = LeaveRequest::update((int)$leave['id'], $payload);
        AuditLogger::log(Auth::user(), 'approve', 'employees', 'leave_request', (int)$leave['id'], $payload, $request->ip(), $request->userAgent());

        return $this->json(['data' => $updated]);
    }

    public function leaveDestroy(Request $request, array $params)
    {
        $leave = LeaveRequest::find((int)$params['leave_id']);
        if (!$leave) {
            return $this->json(['message' => 'Leave request not found'], 404);
        }

        LeaveRequest::delete((int)$leave['id']);
        AuditLogger::log(Auth::user(), 'delete', 'employees', 'leave_request', (int)$leave['id'], [], $request->ip(), $request->userAgent());

        return $this->json(['message' => 'Leave request deleted']);
    }

    public function kpiIndex(Request $request, array $params)
    {
        $employee = Employee::find((int)$params['id']);
        if (!$employee) {
            return $this->json(['message' => 'Employee not found'], 404);
        }

        $pdo = Database::connection();
        $stmt = $pdo->prepare('SELECT * FROM employee_kpis WHERE employee_id = :id ORDER BY period_start DESC');
        $stmt->execute(['id' => $employee['id']]);

        return $this->json(['data' => $stmt->fetchAll(\PDO::FETCH_ASSOC)]);
    }

    public function kpiStore(Request $request, array $params)
    {
        $employee = Employee::find((int)$params['id']);
        if (!$employee) {
            return $this->json(['message' => 'Employee not found'], 404);
        }

        if ($response = $this->validate($request, [
            'period_start' => 'required|date',
            'period_end' => 'required|date',
            'target' => 'required|numeric',
            'achieved' => 'numeric',
            'score' => 'numeric',
        ])) {
            return $response;
        }

        $payload = $request->all();
        $payload['employee_id'] = $employee['id'];
        $kpi = EmployeeKpi::create($payload);

        AuditLogger::log(Auth::user(), 'create', 'employees', 'employee_kpi', (int)$kpi['id'], $payload, $request->ip(), $request->userAgent());

        return $this->json(['data' => $kpi], 201);
    }

    public function verifyDocuments(Request $request, array $params)
    {
        $employee = Employee::find((int)$params['id']);
        if (!$employee) {
            return $this->json(['message' => 'Employee not found'], 404);
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

        $updated = Employee::update((int)$employee['id'], $payload);
        AuditLogger::log(Auth::user(), 'verify_documents', 'employees', 'employee', (int)$employee['id'], $payload, $request->ip(), $request->userAgent());

        return $this->json(['data' => $updated]);
    }

    private function applyGradeSelection(array $payload): array|Response
    {
        $gradeId = (int)($payload['grade_id'] ?? 0);
        $designationId = (int)($payload['designation_id'] ?? 0);
        if ($gradeId <= 0 || $designationId <= 0) {
            return $this->json(['message' => 'Grade and designation are required'], 422);
        }

        $grade = Grade::find($gradeId);
        if (!$grade) {
            return $this->json(['message' => 'Selected grade is invalid'], 422);
        }
        if (isset($grade['status']) && strtolower((string)$grade['status']) !== 'active') {
            return $this->json(['message' => 'Selected grade is inactive'], 422);
        }

        $designation = Designation::find($designationId);
        if (!$designation || (int)$designation['grade_id'] !== (int)$grade['id']) {
            return $this->json(['message' => 'Designation must belong to the selected grade'], 422);
        }
        if (isset($designation['status']) && strtolower((string)$designation['status']) !== 'active') {
            return $this->json(['message' => 'Selected designation is inactive'], 422);
        }

        $payload['grade_id'] = (int)$grade['id'];
        $payload['designation_id'] = (int)$designation['id'];
        $payload['grade'] = $this->formatGradeLabel($grade);
        $payload['position'] = $designation['designation_name'];

        return $payload;
    }

    private function formatGradeLabel(array $grade): string
    {
        $label = 'Grade ' . ($grade['grade_no'] ?? '');
        if (!empty($grade['grade_name'])) {
            $label .= ' - ' . $grade['grade_name'];
        }

        return trim($label, ' -');
    }

    private function preparePayload(array $input): array
    {
        $allowed = [
            'name',
            'father_name',
            'mother_name',
            'nid',
            'address',
            'phone',
            'email',
            'education',
            'qualifications',
            'grade_id',
            'designation_id',
            'join_date',
            'salary',
            'documents',
            'status',
            'document_checklist',
            'photo_path',
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
            if ($key === 'salary' && $value !== null) {
                $filtered[$key] = (float)$value;
            }
            if (in_array($key, ['grade_id', 'designation_id'], true)) {
                $filtered[$key] = (int)$value;
            }
        }

        return $filtered;
    }

    private function handleDocuments(Request $request): array
    {
        $uploader = new DocumentUpload();
        $paths = [];
        $files = [
            'photo' => 'photo_path',
            'nid_document' => 'nid_document_path',
            'police_verification' => 'police_verification_path',
        ];

        foreach ($files as $field => $column) {
            if ($request->hasFile($field)) {
                $paths[$column] = $uploader->store($request->file($field), 'employees');
            }
        }

        return $paths;
    }
}
