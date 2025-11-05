<?php

namespace App\Http\Controllers;

use App\Core\Request;
use App\Models\Account;

class AccountController extends Controller
{
    public function index(): array
    {
        return ['data' => Account::all()];
    }

    public function store(Request $request)
    {
        if ($response = $this->validate($request, [
            'type' => 'required|in:income,expense',
            'description' => 'required',
            'amount' => 'required',
            'date' => 'required',
        ])) {
            return $response;
        }

        $account = Account::create($request->all());
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

        $updated = Account::update((int)$params['id'], $request->all());
        return $this->json(['data' => $updated]);
    }

    public function destroy(Request $request, array $params)
    {
        $deleted = Account::delete((int)$params['id']);
        if (!$deleted) {
            return $this->json(['message' => 'Account record not found'], 404);
        }

        return $this->json(['message' => 'Account record deleted']);
    }
}
