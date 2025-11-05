<?php

namespace App\Http\Controllers;

use App\Core\Request;
use App\Models\Share;

class ShareController extends Controller
{
    public function index(): array
    {
        return ['data' => Share::all()];
    }

    public function store(Request $request)
    {
        if ($response = $this->validate($request, [
            'investor_id' => 'required',
            'unit_price' => 'required',
            'quantity' => 'required',
        ])) {
            return $response;
        }

        $data = $request->all();
        $share = Share::create($data);
        return $this->json(['data' => $share], 201);
    }

    public function show(Request $request, array $params)
    {
        $share = Share::find((int)$params['id']);
        if (!$share) {
            return $this->json(['message' => 'Share not found'], 404);
        }

        return $this->json(['data' => $share]);
    }

    public function update(Request $request, array $params)
    {
        $share = Share::find((int)$params['id']);
        if (!$share) {
            return $this->json(['message' => 'Share not found'], 404);
        }

        $updated = Share::update((int)$params['id'], $request->all());
        return $this->json(['data' => $updated]);
    }

    public function destroy(Request $request, array $params)
    {
        $deleted = Share::delete((int)$params['id']);
        if (!$deleted) {
            return $this->json(['message' => 'Share not found'], 404);
        }

        return $this->json(['message' => 'Share deleted']);
    }
}
