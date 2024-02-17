<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginUserRequest;
use App\Http\Requests\PasswordForgotRequest;
use App\Http\Requests\PasswordResetRequest;
use App\Http\Requests\RegisterUserRequest;
use App\Http\Requests\TokenValidateRequest;
use App\Services\RegistrationService;
use Exception;

class RegistrationController extends Controller
{
    private RegistrationService $registrationService;

    public function __construct(RegistrationService $registrationService)
    {
        $this->middleware('auth:sanctum')->only('logout');

        $this->registrationService = $registrationService;
    }

    public function register(RegisterUserRequest $request)
    {
        return $this->registrationService->register($request->validated());
    }

    public function login(LoginUserRequest $request)
    {
        return $this->registrationService->login($request->validated()['email'], $request->validated()['password']);
    }

    public function logout()
    {
        $this->registrationService->logout();

        return response()->json([
            'message' => 'Logout successful',
        ]);
    }

    public function forgotPassword(PasswordForgotRequest $request)
    {
        return $this->registrationService->forgotPassword($request->validated()['email']);
    }

    public function validateResetToken(TokenValidateRequest $request)
    {
        try {
            return $this->registrationService->validatePasswordToken($request->validated()['email'], $request->validated()['token']);
        } catch (Exception $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], $e->getCode());
        }
    }

    public function resetPassword(PasswordResetRequest $request)
    {
        try {
            return $this->registrationService->resetPassword($request->validated()['email'], $request->validated()['token'], $request->validated()['new_password']);
        } catch (Exception $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], $e->getCode());
        }
    }
}
