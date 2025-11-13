<?php

namespace App\Http\Controllers;

use App\Core\Request;
use App\Models\Account;
use App\Support\AuditLogger;
use App\Support\Auth;

class AccountController extends Controller
{
    public function index(): array
    {
        return ['data' => Account::all()];
    }

    public function store(Request $request)
    {
        if ($response = $this->validate($request, [
            'code' => 'required',
            'name' => 'required',
            'category' => 'required|in:asset,liability,equity,income,expense',
            'parent_id' => '',
            'is_active' => 'boolean',
            'type' => '',
            'description' => '',
            'amount' => '',
            'date' => '',
        ])) {
            return $response;
        }

        $payload = $request->all();
        $payload['is_active'] = $payload['is_active'] ?? 1;
        $account = Account::create($payload);
        AuditLogger::log(Auth::user(), 'create', 'accounts', 'account', (int)$account['id'], $payload, $request->ip(), $request->userAgent());
        return $this->json(['data' => $account], 201);
    }

    public function show(Request $request, array $params)
    {
        $account = Account::find((int)$params['id']);
        if (!$account) {
            return $this->json(['message' => 'Account record not found'], 404);
        }

        return $this->json(['data' => $account]);
    }

    public function update(Request $request, array $params)
    {
        $account = Account::find((int)$params['id']);
        if (!$account) {
            return $this->json(['message' => 'Account record not found'], 404);
        }

        $payload = $request->all();
        $updated = Account::update((int)$params['id'], $payload);
        AuditLogger::log(Auth::user(), 'update', 'accounts', 'account', (int)$params['id'], $payload, $request->ip(), $request->userAgent());
        return $this->json(['data' => $updated]);
    }

    public function destroy(Request $request, array $params)
    {
        $deleted = Account::delete((int)$params['id']);
        if (!$deleted) {
            return $this->json(['message' => 'Account record not found'], 404);
        }

        AuditLogger::log(Auth::user(), 'delete', 'accounts', 'account', (int)$params['id'], [], $request->ip(), $request->userAgent());
        return $this->json(['message' => 'Account record deleted']);
    }
}
