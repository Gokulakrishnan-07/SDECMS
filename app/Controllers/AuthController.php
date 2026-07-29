<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Core\Validator;

class AuthController extends Controller
{
    public function showLogin(): void
    {
        $this->view('auth.login', [], 'auth');
    }

    /**
     * Web form login (POST /login).
     */
    public function login(): void
    {
        $v = Validator::make(Request::all(), [
            'email'    => 'required|email|max:150',
            'password' => 'required|minlen:6',
        ]);

        if ($v->fails()) {
            flash('error', 'Please enter a valid email and password.');
            redirect('login');
        }

        $data = $v->validated();
        if (!Auth::attempt($data['email'], $data['password'])) {
            flash('error', 'Invalid credentials or the account is disabled.');
            redirect('login');
        }

        redirect('dashboard');
    }

    /**
     * API login (POST /api/login) — same session auth, JSON envelope.
     */
    public function apiLogin(): void
    {
        $v = Validator::make(Request::all(), [
            'email'    => 'required|email|max:150',
            'password' => 'required|minlen:6',
        ]);

        if ($v->fails()) {
            Response::error('Validation failed.', 422, $v->errors());
        }

        $data = $v->validated();
        if (!Auth::attempt($data['email'], $data['password'])) {
            Response::error('Invalid credentials or the account is disabled.', 401);
        }

        $user = Auth::user();
        Response::json([
            'id'         => (int) $user['id'],
            'name'       => $user['name'],
            'email'      => $user['email'],
            'role'       => $user['role'],
            'csrf_token' => csrf_token(),
        ], 200, 'Login successful.');
    }

    public function logout(): void
    {
        Auth::logout();
        redirect('login');
    }

    public function apiLogout(): void
    {
        Auth::logout();
        Response::json(null, 200, 'Logged out.');
    }
}
