<?php

namespace App\Http\Controllers;

use App\Core\Database;
use App\Core\Request;
use App\Models\Policy;
use App\Models\PolicyChange;
use App\Support\AuditLogger;
use App\Support\Auth;
use App\Support\Validator;

class PolicyController extends Controller
{
    public function index(): array
    {
        return ['data' => Policy::all()];
    }

    public function store(Request $request)
    {
        if ($response = $this->validate($request, [
            'code' => 'required|unique:policies,code',
            'title' => 'required',
            'description' => '',
            'version' => 'required',
            'effective_from' => 'date',
            'effective_to' => 'date',
            'status' => 'in:draft,active,archived',
        ])) {
            return $response;
        }

        $payload = $request->all();
        $payload['status'] = $payload['status'] ?? 'draft';
        $payload['created_by'] = Auth::user()['id'] ?? null;
        $payload['updated_by'] = Auth::user()['id'] ?? null;

        $policy = Policy::create($payload);
        AuditLogger::log(Auth::user(), 'create', 'policies', 'policy', (int)$policy['id'], $payload, $request->ip(), $request->userAgent());

        return $this->json(['data' => $policy], 201);
    }

    public function show(Request $request, array $params)
    {
        $policy = Policy::find((int)$params['id']);
        if (!$policy) {
            return $this->json(['message' => 'Policy not found'], 404);
        }

        return $this->json(['data' => $policy]);
    }

    public function update(Request $request, array $params)
    {
        $policy = Policy::find((int)$params['id']);
        if (!$policy) {
            return $this->json(['message' => 'Policy not found'], 404);
        }

        if ($response = $this->validate($request, [
            'code' => 'required|unique:policies,code,' . $policy['id'],
            'title' => 'required',
            'description' => '',
            'version' => 'required',
            'effective_from' => 'date',
            'effective_to' => 'date',
            'status' => 'in:draft,active,archived',
        ])) {
            return $response;
        }

        $payload = array_merge($policy, $request->all());
        $payload['updated_by'] = Auth::user()['id'] ?? null;

        $updated = Policy::update((int)$params['id'], $payload);

        PolicyChange::create([
            'policy_id' => $policy['id'],
            'change_summary' => $request->input('change_summary') ?? 'Policy updated',
            'old_value_json' => json_encode($policy),
            'new_value_json' => json_encode($updated),
            'approved_by' => json_encode($request->input('approved_by') ?? []),
        ]);

        AuditLogger::log(Auth::user(), 'update', 'policies', 'policy', (int)$params['id'], $payload, $request->ip(), $request->userAgent());

        return $this->json(['data' => $updated]);
    }

    public function destroy(Request $request, array $params)
    {
        $policy = Policy::find((int)$params['id']);
        if (!$policy) {
            return $this->json(['message' => 'Policy not found'], 404);
        }

        Policy::delete((int)$params['id']);
        AuditLogger::log(Auth::user(), 'delete', 'policies', 'policy', (int)$params['id'], [], $request->ip(), $request->userAgent());

        return $this->json(['message' => 'Policy deleted']);
    }

    public function history(Request $request, array $params)
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare('SELECT * FROM policy_changes WHERE policy_id = :id ORDER BY created_at DESC');
        $stmt->execute(['id' => (int)$params['id']]);

        return $this->json(['data' => $stmt->fetchAll(\PDO::FETCH_ASSOC)]);
    }
}
