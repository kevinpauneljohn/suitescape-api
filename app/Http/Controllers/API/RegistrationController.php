<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginUserRequest;
use App\Http\Requests\RegisterUserRequest;
use App\Services\RegistrationService;

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
        return $this->registrationService->login($request->validated());
    }

    public function logout()
    {
        return $this->registrationService->logout();
    }
}
