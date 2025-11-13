<?php

namespace App\Http\Controllers;

use App\Core\Request;
use App\Models\Approval;
use App\Support\AuditLogger;
use App\Support\Auth;

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

        $payload = $request->all();
        $approval = Approval::create($payload);
        AuditLogger::log(Auth::user(), 'create', 'approvals', 'approval', (int)$approval['id'], $payload, $request->ip(), $request->userAgent());
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

        $payload = $request->all();
        $updated = Approval::update((int)$params['id'], $payload);
        AuditLogger::log(Auth::user(), 'update', 'approvals', 'approval', (int)$params['id'], $payload, $request->ip(), $request->userAgent());
        return $this->json(['data' => $updated]);
    }

    public function destroy(Request $request, array $params)
    {
        $deleted = Approval::delete((int)$params['id']);
        if (!$deleted) {
            return $this->json(['message' => 'Approval not found'], 404);
        }

        AuditLogger::log(Auth::user(), 'delete', 'approvals', 'approval', (int)$params['id'], [], $request->ip(), $request->userAgent());
        return $this->json(['message' => 'Approval deleted']);
    }
}
