<?php

namespace App\Http\Controllers\API;

use App\Helpers\RegistrationService;
use App\Http\Controllers\Controller;
use App\Http\Requests\LoginUserRequest;
use App\Http\Requests\RegisterUserRequest;
use Illuminate\Http\Request;

class RegistrationController extends Controller
{
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
        $request->user()->tokens()->delete();

        return response()->json([
            'message' => 'Logout successful',
        ]);
    }
}
