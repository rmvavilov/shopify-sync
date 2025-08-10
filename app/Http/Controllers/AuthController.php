<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email'    => ['required','email'],
            'password' => ['required'],
        ]);

        if (Auth::guard('web')->attempt($credentials, true)) {
            $request->session()->regenerate();

            return response()->json(['message' => 'ok']);
        }

        return response()->json(['message' => 'Invalid credentials'], 422);
    }

    public function logout(Request $request)
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

//        return response()->json(['message' => 'ok']);
        return response()->noContent();
    }

    public function me(Request $request)
    {
        if (Auth::guard('web')->check()) {
            return response()->json(Auth::guard('web')->user());
        }

        return response()->json(['message' => 'Unauthenticated'], 401);
    }
}
