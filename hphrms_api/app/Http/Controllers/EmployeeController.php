<?php

namespace App\Http\Controllers;

use App\Core\Request;
use App\Models\Employee;
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
            'grade' => 'required',
            'position' => 'required',
            'join_date' => 'required|date',
            'salary' => 'required|numeric|min:0',
            'documents' => 'array|min:1',
            'status' => 'in:' . implode(',', self::STATUSES),
        ])) {
            return $response;
        }

        $payload = $this->preparePayload($request->all());
        $payload['status'] = $payload['status'] ?? 'active';

        if (isset($payload['documents']) && is_array($payload['documents'])) {
            $payload['document_checklist'] = json_encode($payload['documents']);
            unset($payload['documents']);
        }

        $employee = Employee::create($payload);
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
            'grade' => 'required',
            'position' => 'required',
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
        $payload['status'] = $payload['status'] ?? 'active';
        if (isset($payload['documents']) && is_array($payload['documents'])) {
            $payload['document_checklist'] = json_encode($payload['documents']);
            unset($payload['documents']);
        }

        $updated = Employee::update((int)$params['id'], $payload);
        return $this->json(['data' => $updated]);
    }

    public function destroy(Request $request, array $params)
    {
        $deleted = Employee::delete((int)$params['id']);
        if (!$deleted) {
            return $this->json(['message' => 'Employee not found'], 404);
        }

        return $this->json(['message' => 'Employee deleted']);
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
            'grade',
            'position',
            'join_date',
            'salary',
            'documents',
            'status',
            'document_checklist',
            'photo_path',
        ];

        $filtered = array_intersect_key($input, array_flip($allowed));
        foreach ($filtered as $key => $value) {
            if (is_string($value)) {
                $filtered[$key] = trim($value);
            }
            if ($key === 'salary' && $value !== null) {
                $filtered[$key] = (float)$value;
            }
        }

        return $filtered;
    }
}
