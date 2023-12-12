<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;

class RegistrationService
{
    public array $userData;

    public function __construct($userData)
    {
        $this->userData = $userData;
    }

    public function register(): JsonResponse
    {
        $user = User::create($this->userData);

        return response()->json([
            'message' => 'User created successfully',
            'user' => $user,
        ]);
    }

    public function login(): JsonResponse
    {
        $user = $this->getUserByEmail();

        if (! $user) {
            return response()->json([
                'message' => 'The provided credentials are incorrect.',
                'errors' => [
                    'email' => ['The provided credentials are incorrect.'],
                ],
            ]);
        }

        if (! $this->checkIfPasswordIsCorrect($user->password)) {
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

    public function getUserByEmail(): ?User
    {
        return User::where('email', $this->userData['email'])->first();
    }

    public function checkIfPasswordIsCorrect($password): bool
    {
        return Hash::check($this->userData['password'], $password);
    }
}
