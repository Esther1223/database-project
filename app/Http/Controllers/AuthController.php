<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    /**
     * Handle login request
     */
    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json([
                'message' => '帳號或密碼不正確',
            ], 401);
        }

        Auth::login($user);

        return response()->json([
            'message' => '登入成功',
            'user' => $user,
        ]);
    }

    /**
     * Handle logout request
     */
    public function logout(Request $request)
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json([
            'message' => '登出成功',
        ]);
    }

    /**
     * Get current user
     */
    public function user(Request $request)
    {
        if ($request->user()) {
            return response()->json($request->user());
        }

        return response()->json(null, 401);
    }
}
