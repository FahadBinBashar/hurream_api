<?php

namespace App\Http\Controllers;

use App\Core\Request;
use App\Models\Employee;

class EmployeeController extends Controller
{
    public function index(): array
    {
        return ['data' => Employee::all()];
    }

    public function store(Request $request)
    {
        if ($response = $this->validate($request, [
            'name' => 'required',
            'grade' => 'required',
            'position' => 'required',
            'salary' => 'required',
            'join_date' => 'required',
        ])) {
            return $response;
        }

        $employee = Employee::create($request->all());
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

        $updated = Employee::update((int)$params['id'], $request->all());
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
}
