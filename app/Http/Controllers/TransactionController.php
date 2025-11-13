<?php

namespace App\Http\Controllers;

use App\Core\Request;
use App\Models\Transaction;

class TransactionController extends Controller
{
    public function index(): array
    {
        return ['data' => Transaction::all()];
    }

    public function store(Request $request)
    {
        if ($response = $this->validate($request, [
            'share_id' => 'required',
            'amount' => 'required',
            'payment_type' => 'required',
            'date' => 'required',
        ])) {
            return $response;
        }

        $transaction = Transaction::create($request->all());
        return $this->json(['data' => $transaction], 201);
    }

    public function show(Request $request, array $params)
    {
        $transaction = Transaction::find((int)$params['id']);
        if (!$transaction) {
            return $this->json(['message' => 'Transaction not found'], 404);
        }

        return $this->json(['data' => $transaction]);
    }

    public function update(Request $request, array $params)
    {
        $transaction = Transaction::find((int)$params['id']);
        if (!$transaction) {
            return $this->json(['message' => 'Transaction not found'], 404);
        }

        $updated = Transaction::update((int)$params['id'], $request->all());
        return $this->json(['data' => $updated]);
    }

    public function destroy(Request $request, array $params)
    {
        $deleted = Transaction::delete((int)$params['id']);
        if (!$deleted) {
            return $this->json(['message' => 'Transaction not found'], 404);
        }

        return $this->json(['message' => 'Transaction deleted']);
    }
}
