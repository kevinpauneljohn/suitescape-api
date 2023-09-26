<?php

namespace App\Http\Controllers\API;

use App\Services\RegistrationService;
use App\Http\Controllers\Controller;
use App\Http\Requests\LoginUserRequest;
use App\Http\Requests\RegisterUserRequest;
use Illuminate\Http\Request;

class RegistrationController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum')->only('logout');
    }

    public function register(RegisterUserRequest $request)
    {
        return (new RegistrationService($request->validated()))->register();
    }

    public function login(LoginUserRequest $request)
    {
        return (new RegistrationService($request->validated()))->login();
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => 'Logout successful',
        ]);
    }
}
