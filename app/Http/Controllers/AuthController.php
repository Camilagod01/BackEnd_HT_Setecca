<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    /**
     * POST /api/login
     * Body: { "email": "...", "password": "..." }
     * Respuesta: { user, token }
     */
    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email'    => ['required','email'],
            'password' => ['required','string'],
        ]);

        if (!Auth::attempt($credentials)) {
            return response()->json(['message' => 'Credenciales inválidas'], 401);
        }

        /** @var \App\Models\User $user */
        $user = Auth::user();

        // Opcional: revocar tokens anteriores para 1-solo-token-activo
        $user->tokens()->delete();

        $token = $user->createToken('api-token')->plainTextToken;

        return response()->json([
            'user'  => $user,
            'token' => $token,
        ]);
    }

    /**
     * POST /api/logout
     * Header: Authorization: Bearer <token>
     */
    public function logout(Request $request)
    {
        /** @var \App\Models\User $user */
        $user = $request->user();

        // Revoca solo el token usado en esta petición
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Sesión cerrada']);
    }

    /**
     * GET /api/me
     * Header: Authorization: Bearer <token>
     */
    public function me(Request $request)
    {
        return response()->json($request->user());
    }
}