<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Role;
use App\Models\UserStatus;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Carbon\Carbon;

class AuthenticationController extends Controller
{
    public function registerAccount(Request $request)
    {
        $request->validate([
            'first_name' => ['required', 'string', 'max:255'],
            'middle_name' => ['nullable', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'contact_number' => ['required', 'string', 'max:20', 'unique:users'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        $staffRole = Role::where('name', 'Staff')->first();
        $activeStatus = UserStatus::where('name', 'Active')->first();

        $user = User::create([
            'first_name' => $request->first_name,
            'middle_name' => $request->middle_name,
            'last_name' => $request->last_name,
            'email' => $request->email,
            'contact_number' => $request->contact_number,
            'password' => Hash::make($request->password),
            'role_id' => $staffRole->id,
            'user_status_id' => $activeStatus->id,
        ]);

        return response()->json(['message' => 'User registered successfully', 'user' => $user], 200);
    }

    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        $user = User::where('email', $request->email)->with('role', 'status')->first();

        if (! $user || ! Hash::check($request->password, $user->password)) {
            return response()->json(['message' => 'The email or password are incorrect.'], 401);
        }

        if (! $user->isActive()) {
            return response()->json(['message' => 'Your account is deactivated. Please contact the administrator.'], 403);
        }

        $token = $user->createToken('auth_token', ['*'], now()->addHours(8))->plainTextToken;

        return response()->json([
            'user' => $user,
            'token' => $token
        ], 200);
    }
    
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Logged out successfully'], 200);
    }
}