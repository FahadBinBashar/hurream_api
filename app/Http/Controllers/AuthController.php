<?php

namespace App\Http\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Models\User;
use App\Support\Auth;
use App\Support\Token;
use App\Support\Validator;

class AuthController
{
    public function register(Request $request): Response
    {
        $data = $request->all();
        $errors = Validator::make($data, [
            'name' => 'required',
            'email' => 'required|email',
            'password' => 'required',
        ]);

        if (!empty($errors)) {
            return new Response(['message' => 'Validation failed', 'errors' => $errors], 422);
        }

        $existing = User::all();
        foreach ($existing as $user) {
            if ($user['email'] === $data['email']) {
                return new Response(['message' => 'Email already registered'], 409);
            }
        }

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => password_hash($data['password'], PASSWORD_BCRYPT),
            'role' => $data['role'] ?? 'customer',
            'grade_id' => $data['grade_id'] ?? null,
            'NID' => $data['NID'] ?? null,
            'phone' => $data['phone'] ?? null,
        ]);

        $token = Token::create($user['id']);
        unset($user['password']);

        return new Response([
            'message' => 'Registration successful',
            'user' => $user,
            'token' => $token,
        ], 201);
    }

    public function login(Request $request): Response
    {
        $data = $request->all();
        $errors = Validator::make($data, [
            'email' => 'required|email',
            'password' => 'required',
        ]);

        if (!empty($errors)) {
            return new Response(['message' => 'Validation failed', 'errors' => $errors], 422);
        }

        $user = Auth::attempt($data['email'], $data['password']);
        if (!$user) {
            return new Response(['message' => 'Invalid credentials'], 401);
        }

        $token = Token::create($user['id']);
        unset($user['password']);

        return new Response([
            'message' => 'Login successful',
            'user' => $user,
            'token' => $token,
        ]);
    }

    public function logout(Request $request): Response
    {
        $user = Auth::user();
        if ($user) {
            Token::deleteForUser((int)$user['id']);
        }

        return new Response(['message' => 'Logged out successfully']);
    }
}
