<?php

namespace App\Http\Controllers;

use App\Core\Request;
use App\Models\Lead;

class LeadController extends Controller
{
    public function index(): array
    {
        return ['data' => Lead::all()];
    }

    public function store(Request $request)
    {
        if ($response = $this->validate($request, [
            'officer_id' => 'required',
            'name' => 'required',
            'contact' => 'required',
        ])) {
            return $response;
        }

        $lead = Lead::create($request->all());
        return $this->json(['data' => $lead], 201);
    }

    public function show(Request $request, array $params)
    {
        $lead = Lead::find((int)$params['id']);
        if (!$lead) {
            return $this->json(['message' => 'Lead not found'], 404);
        }

        return $this->json(['data' => $lead]);
    }

    public function update(Request $request, array $params)
    {
        $lead = Lead::find((int)$params['id']);
        if (!$lead) {
            return $this->json(['message' => 'Lead not found'], 404);
        }

        $updated = Lead::update((int)$params['id'], $request->all());
        return $this->json(['data' => $updated]);
    }

    public function destroy(Request $request, array $params)
    {
        $deleted = Lead::delete((int)$params['id']);
        if (!$deleted) {
            return $this->json(['message' => 'Lead not found'], 404);
        }

        return $this->json(['message' => 'Lead deleted']);
    }
}
