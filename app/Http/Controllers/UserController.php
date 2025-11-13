<?php

namespace App\Http\Controllers;

use App\Core\Request;
use App\Models\User;

class UserController extends Controller
{
    public function index(): array
    {
        $users = array_map(function ($user) {
            unset($user['password']);
            return $user;
        }, User::all());

        return ['data' => $users];
    }

    public function store(Request $request)
    {
        if ($response = $this->validate($request, [
            'name' => 'required',
            'email' => 'required|email',
            'password' => 'required',
            'role' => 'required',
        ])) {
            return $response;
        }

        $data = $request->all();
        foreach (User::all() as $existing) {
            if ($existing['email'] === $data['email']) {
                return $this->json(['message' => 'Email already exists'], 409);
            }
        }
        $data['password'] = password_hash($data['password'], PASSWORD_BCRYPT);
        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $data['password'],
            'role' => $data['role'],
            'grade_id' => $data['grade_id'] ?? null,
            'NID' => $data['NID'] ?? null,
            'phone' => $data['phone'] ?? null,
        ]);
        unset($user['password']);

        return $this->json(['data' => $user], 201);
    }

    public function show(Request $request, array $params)
    {
        $user = User::find((int)$params['id']);
        if (!$user) {
            return $this->json(['message' => 'User not found'], 404);
        }
        unset($user['password']);
        return $this->json(['data' => $user]);
    }

    public function update(Request $request, array $params)
    {
        $user = User::find((int)$params['id']);
        if (!$user) {
            return $this->json(['message' => 'User not found'], 404);
        }

        $data = $request->all();
        if (isset($data['password'])) {
            $data['password'] = password_hash($data['password'], PASSWORD_BCRYPT);
        }
        $updated = User::update((int)$params['id'], $data);
        unset($updated['password']);

        return $this->json(['data' => $updated]);
    }

    public function destroy(Request $request, array $params)
    {
        $deleted = User::delete((int)$params['id']);
        if (!$deleted) {
            return $this->json(['message' => 'User not found'], 404);
        }

        return $this->json(['message' => 'User deleted']);
    }
}
