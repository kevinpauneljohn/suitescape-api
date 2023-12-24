<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;

class RegistrationService
{
    public function register($registrationData): JsonResponse
    {
        $user = User::create($registrationData);

        return response()->json([
            'message' => 'User created successfully',
            'user' => $user,
        ]);
    }

    public function login($loginData): JsonResponse
    {
        $user = $this->getUserByEmail($loginData['email']);

        if (! $user) {
            return response()->json([
                'message' => 'The provided credentials are incorrect.',
                'errors' => [
                    'email' => ['The provided credentials are incorrect.'],
                ],
            ]);
        }

        if (! $this->checkIfPasswordIsCorrect($loginData['password'], $user->password)) {
            return response()->json([
                'message' => 'The provided password is incorrect.',
                'errors' => [
                    'password' => ['The provided password is incorrect.'],
                ],
            ]);
        }

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'message' => 'Login successful',
            'token' => $token,
        ]);
    }

    public function logout(): JsonResponse
    {
        $user = auth()->user();

        $user->currentAccessToken()->delete();

        return response()->json([
            'message' => 'Logout successful',
        ]);
    }

    public function getUserByEmail($userEmail): ?User
    {
        return User::where('email', $userEmail)->first();
    }

    public function checkIfPasswordIsCorrect($userPassword, $correctPassword): bool
    {
        return Hash::check($userPassword, $correctPassword);
    }
}
