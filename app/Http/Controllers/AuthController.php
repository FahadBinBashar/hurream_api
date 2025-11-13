<?php

namespace App\Http\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Models\User;
use App\Support\AuditLogger;
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

        AuditLogger::log($user, 'register', 'auth', 'user', (int)$user['id'], $request->all(), $request->ip(), $request->userAgent());

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

        if ($this->shouldRequireOtp($user)) {
            $otpCode = (string)random_int(100000, 999999);
            User::update((int)$user['id'], [
                'otp_code' => password_hash($otpCode, PASSWORD_BCRYPT),
                'otp_expires_at' => date('Y-m-d H:i:s', strtotime('+10 minutes')),
                'two_factor_type' => $user['two_factor_type'] ?? 'email',
            ]);

            AuditLogger::log($user, 'otp_requested', 'auth', 'user', (int)$user['id'], ['context' => 'login'], $request->ip(), $request->userAgent());

            return new Response([
                'message' => 'OTP required. Please verify to continue.',
                'requires_otp' => true,
                'delivery' => $user['two_factor_type'] ?? 'email',
            ]);
        }

        $token = Token::create($user['id']);
        unset($user['password']);

        AuditLogger::log($user, 'login', 'auth', 'user', (int)$user['id'], $request->all(), $request->ip(), $request->userAgent());

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
            AuditLogger::log($user, 'logout', 'auth', 'user', (int)$user['id'], [], $request->ip(), $request->userAgent());
        }

        return new Response(['message' => 'Logged out successfully']);
    }

    public function requestOtp(Request $request): Response
    {
        $data = $request->all();
        $errors = Validator::make($data, [
            'email' => 'required|email',
            'type' => '',
        ]);

        if (!empty($errors)) {
            return new Response(['message' => 'Validation failed', 'errors' => $errors], 422);
        }

        $user = User::findByEmail($data['email']);
        if (!$user) {
            return new Response(['message' => 'User not found'], 404);
        }

        $otpCode = (string)random_int(100000, 999999);
        $type = $data['type'] ?? ($user['two_factor_type'] ?? 'email');
        User::update((int)$user['id'], [
            'otp_code' => password_hash($otpCode, PASSWORD_BCRYPT),
            'otp_expires_at' => date('Y-m-d H:i:s', strtotime('+10 minutes')),
            'two_factor_type' => $type,
        ]);

        AuditLogger::log($user, 'otp_requested', 'auth', 'user', (int)$user['id'], ['context' => 'manual'], $request->ip(), $request->userAgent());

        return new Response([
            'message' => 'OTP generated successfully',
            'delivery' => $type,
        ]);
    }

    public function verifyOtp(Request $request): Response
    {
        $data = $request->all();
        $errors = Validator::make($data, [
            'email' => 'required|email',
            'otp' => 'required',
            'two_factor_type' => '',
        ]);

        if (!empty($errors)) {
            return new Response(['message' => 'Validation failed', 'errors' => $errors], 422);
        }

        $user = User::findByEmail($data['email']);
        if (!$user) {
            return new Response(['message' => 'User not found'], 404);
        }

        if (empty($user['otp_code']) || empty($user['otp_expires_at'])) {
            return new Response(['message' => 'No OTP request pending'], 400);
        }

        if (strtotime((string)$user['otp_expires_at']) < time()) {
            return new Response(['message' => 'OTP expired'], 400);
        }

        if (!password_verify($data['otp'], $user['otp_code'])) {
            return new Response(['message' => 'Invalid OTP'], 400);
        }

        $type = $data['two_factor_type'] ?? ($user['two_factor_type'] ?? 'email');
        $updated = User::update((int)$user['id'], [
            'otp_code' => null,
            'otp_expires_at' => null,
            'two_factor_enabled' => 1,
            'two_factor_type' => $type,
        ]);

        $token = Token::create((int)$user['id']);
        unset($updated['password']);

        AuditLogger::log($user, 'otp_verified', 'auth', 'user', (int)$user['id'], [], $request->ip(), $request->userAgent());

        return new Response([
            'message' => 'OTP verified successfully',
            'user' => $updated,
            'token' => $token,
        ]);
    }

    public function forgotPassword(Request $request): Response
    {
        $data = $request->all();
        $errors = Validator::make($data, [
            'email' => 'required|email',
        ]);

        if (!empty($errors)) {
            return new Response(['message' => 'Validation failed', 'errors' => $errors], 422);
        }

        $user = User::findByEmail($data['email']);
        if (!$user) {
            return new Response(['message' => 'User not found'], 404);
        }

        $otpCode = (string)random_int(100000, 999999);
        User::update((int)$user['id'], [
            'otp_code' => password_hash($otpCode, PASSWORD_BCRYPT),
            'otp_expires_at' => date('Y-m-d H:i:s', strtotime('+15 minutes')),
        ]);

        AuditLogger::log($user, 'forgot_password', 'auth', 'user', (int)$user['id'], [], $request->ip(), $request->userAgent());

        return new Response(['message' => 'Password reset OTP sent']);
    }

    public function resetPassword(Request $request): Response
    {
        $data = $request->all();
        $errors = Validator::make($data, [
            'email' => 'required|email',
            'otp' => 'required',
            'password' => 'required',
        ]);

        if (!empty($errors)) {
            return new Response(['message' => 'Validation failed', 'errors' => $errors], 422);
        }

        $user = User::findByEmail($data['email']);
        if (!$user) {
            return new Response(['message' => 'User not found'], 404);
        }

        if (empty($user['otp_code']) || empty($user['otp_expires_at'])) {
            return new Response(['message' => 'OTP not available'], 400);
        }

        if (strtotime((string)$user['otp_expires_at']) < time()) {
            return new Response(['message' => 'OTP expired'], 400);
        }

        if (!password_verify($data['otp'], $user['otp_code'])) {
            return new Response(['message' => 'Invalid OTP'], 400);
        }

        User::update((int)$user['id'], [
            'password' => password_hash($data['password'], PASSWORD_BCRYPT),
            'otp_code' => null,
            'otp_expires_at' => null,
        ]);

        AuditLogger::log($user, 'password_reset', 'auth', 'user', (int)$user['id'], [], $request->ip(), $request->userAgent());

        return new Response(['message' => 'Password reset successful']);
    }

    protected function shouldRequireOtp(array $user): bool
    {
        $role = strtolower($user['role'] ?? '');
        if (in_array($role, ['admin', 'finance'], true)) {
            return true;
        }

        return !empty($user['two_factor_enabled']);
    }
}
