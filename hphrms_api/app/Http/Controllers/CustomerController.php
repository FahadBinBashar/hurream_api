<?php

namespace App\Http\Controllers;

use App\Core\Request;
use App\Models\Customer;

class CustomerController extends Controller
{
    public function index(): array
    {
        return ['data' => Customer::all()];
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

        $customer = Customer::create($request->all());
        return $this->json(['data' => $customer], 201);
    }

    public function show(Request $request, array $params)
    {
        $customer = Customer::find((int)$params['id']);
        if (!$customer) {
            return $this->json(['message' => 'Customer not found'], 404);
        }

        return $this->json(['data' => $customer]);
    }

    public function update(Request $request, array $params)
    {
        $customer = Customer::find((int)$params['id']);
        if (!$customer) {
            return $this->json(['message' => 'Customer not found'], 404);
        }

        $updated = Customer::update((int)$params['id'], $request->all());
        return $this->json(['data' => $updated]);
    }

    public function destroy(Request $request, array $params)
    {
        $deleted = Customer::delete((int)$params['id']);
        if (!$deleted) {
            return $this->json(['message' => 'Customer not found'], 404);
        }

        return $this->json(['message' => 'Customer deleted']);
    }
}
