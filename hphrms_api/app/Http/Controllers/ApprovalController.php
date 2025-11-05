<?php

namespace App\Http\Controllers;

use App\Core\Request;
use App\Models\Approval;

class ApprovalController extends Controller
{
    public function index(): array
    {
        return ['data' => Approval::all()];
    }

    public function store(Request $request)
    {
        if ($response = $this->validate($request, [
            'module' => 'required',
            'record_id' => 'required',
            'approver_id' => 'required',
            'status' => 'required',
        ])) {
            return $response;
        }

        $approval = Approval::create($request->all());
        return $this->json(['data' => $approval], 201);
    }

    public function show(Request $request, array $params)
    {
        $approval = Approval::find((int)$params['id']);
        if (!$approval) {
            return $this->json(['message' => 'Approval not found'], 404);
        }

        return $this->json(['data' => $approval]);
    }

    public function update(Request $request, array $params)
    {
        $approval = Approval::find((int)$params['id']);
        if (!$approval) {
            return $this->json(['message' => 'Approval not found'], 404);
        }

        $updated = Approval::update((int)$params['id'], $request->all());
        return $this->json(['data' => $updated]);
    }

    public function destroy(Request $request, array $params)
    {
        $deleted = Approval::delete((int)$params['id']);
        if (!$deleted) {
            return $this->json(['message' => 'Approval not found'], 404);
        }

        return $this->json(['message' => 'Approval deleted']);
    }
}
