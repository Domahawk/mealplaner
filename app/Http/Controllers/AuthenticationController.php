<?php

namespace App\Http\Controllers;

use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegisterRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class AuthenticationController extends Controller
{
    public function session(Request $request): JsonResponse
    {
        return response()->json(['user' => $request->user()?->only(['id', 'name', 'email'])])->header('Cache-Control', 'no-store, private');
    }

    public function register(RegisterRequest $request): JsonResponse
    {
        abort_if(Auth::check(), 409, 'You are already signed in.');

        $user = User::create($request->safe()->only(['name', 'email', 'password']));
        Auth::login($user);
        $request->session()->regenerate();

        return response()->json(['user' => $user->only(['id', 'name', 'email'])], 201)->header('Cache-Control', 'no-store, private');
    }

    public function login(LoginRequest $request): JsonResponse
    {
        abort_if(Auth::check(), 409, 'You are already signed in.');

        if (! Auth::attempt($request->safe()->only(['email', 'password']))) {
            throw ValidationException::withMessages(['email' => 'The provided credentials are incorrect.']);
        }

        $request->session()->regenerate();

        return $this->session($request);
    }

    public function logout(Request $request): Response
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->noContent()->header('Cache-Control', 'no-store, private');
    }
}
