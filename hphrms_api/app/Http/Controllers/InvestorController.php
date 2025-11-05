<?php

namespace App\Http\Controllers;

use App\Core\Database;
use App\Core\Request;
use App\Models\Investor;
use App\Models\Share;

class InvestorController extends Controller
{
    public function index(): array
    {
        $investors = Investor::all();
        $pdo = Database::connection();
        foreach ($investors as &$investor) {
            $stmt = $pdo->prepare('SELECT * FROM shares WHERE investor_id = :id');
            $stmt->execute(['id' => $investor['id']]);
            $investor['shares'] = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        }

        return ['data' => $investors];
    }

    public function store(Request $request)
    {
        if ($response = $this->validate($request, [
            'name' => 'required',
            'NID' => 'required',
            'phone' => 'required',
        ])) {
            return $response;
        }

        $investor = Investor::create($request->all());
        $investor['shares'] = [];
        return $this->json(['data' => $investor], 201);
    }

    public function show(Request $request, array $params)
    {
        $investor = Investor::find((int)$params['id']);
        if (!$investor) {
            return $this->json(['message' => 'Investor not found'], 404);
        }

        $pdo = Database::connection();
        $stmt = $pdo->prepare('SELECT * FROM shares WHERE investor_id = :id');
        $stmt->execute(['id' => $investor['id']]);
        $investor['shares'] = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        return $this->json(['data' => $investor]);
    }

    public function update(Request $request, array $params)
    {
        $investor = Investor::find((int)$params['id']);
        if (!$investor) {
            return $this->json(['message' => 'Investor not found'], 404);
        }

        $updated = Investor::update((int)$params['id'], $request->all());
        return $this->json(['data' => $updated]);
    }

    public function destroy(Request $request, array $params)
    {
        $deleted = Investor::delete((int)$params['id']);
        if (!$deleted) {
            return $this->json(['message' => 'Investor not found'], 404);
        }

        return $this->json(['message' => 'Investor deleted']);
    }
}
