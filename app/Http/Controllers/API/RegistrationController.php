<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginUserRequest;
use App\Http\Requests\PasswordForgotRequest;
use App\Http\Requests\PasswordResetRequest;
use App\Http\Requests\RegisterUserRequest;
use App\Http\Requests\TokenValidateRequest;
use App\Models\User;
use App\Services\RegistrationService;
use Exception;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\Request;

class RegistrationController extends Controller
{
    private RegistrationService $registrationService;

    public function __construct(RegistrationService $registrationService)
    {
        $this->middleware('auth:sanctum')->only(['resendEmail', 'logout']);
        $this->middleware('signed')->only('verifyEmail');

        $this->registrationService = $registrationService;
    }

    /**
     * Register a new user.
     *
     * Validates the incoming request data and registers a new user based on the provided information.
     * Returns a JSON response indicating the success of the registration process.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function register(RegisterUserRequest $request)
    {
        return $this->registrationService->register($request->validated());
    }

    /**
     * User login.
     *
     * Validates the incoming request data for email and password, and attempts to log the user in.
     * Returns a JSON response with login status and user information on success.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function login(LoginUserRequest $request)
    {
        return $this->registrationService->login($request->validated('email'), $request->validated('password'));
    }

    /**
     * User logout.
     *
     * Logs out the currently authenticated user by invalidating their session/token.
     * Returns a JSON response indicating the user has been successfully logged out.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function logout()
    {
        $this->registrationService->logout();

        return response()->json([
            'message' => 'Logout successful',
        ]);
    }

    /**
     * Forgot password.
     *
     * Initiates the password reset process for a user by validating the provided email address
     * and sending a password reset link if the email is associated with an account.
     * Returns a JSON response indicating the status of the password reset request.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function forgotPassword(PasswordForgotRequest $request)
    {
        return $this->registrationService->forgotPassword($request->validated('email'));
    }

    /**
     * Validate password reset token.
     *
     * Validates the password reset token for the given email address to ensure it's valid and has not expired.
     * Returns a JSON response indicating the validity of the token.
     *
     * @return \Illuminate\Http\JsonResponse|object
     */
    public function validateResetToken(TokenValidateRequest $request)
    {
        try {
            return $this->registrationService->validatePasswordToken($request->validated('email'), $request->validated('token'));
        } catch (Exception $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], $e->getCode());
        }
    }

    /**
     * Reset password.
     *
     * Resets the user's password to the new password provided in the request, after validating
     * the password reset token and ensuring it matches the user's email address.
     * Returns a JSON response indicating the success of the password reset operation.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function resetPassword(PasswordResetRequest $request)
    {
        try {
            return $this->registrationService->resetPassword($request->validated('email'), $request->validated('token'), $request->validated('new_password'));
        } catch (Exception $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], $e->getCode());
        }
    }

    /**
     * Verify the user's email address.
     *
     * Marks the user's email as verified after clicking the verification link sent to their email.
     * Returns a JSON response indicating the email has been successfully verified.
     *
     * @return \Illuminate\View\View
     */
    public function verifyEmail(Request $request)
    {
        $user = User::findOrfail($request->id);
        $isAlreadyVerified = $user->hasVerifiedEmail();

        if (! $isAlreadyVerified && $user->markEmailAsVerified()) {
            event(new Verified($request->user()));
        }

        return view('auth.verify-email', [
            'isAlreadyVerified' => $isAlreadyVerified,
        ]);
    }

    /**
     * Resend the email verification link.
     *
     * Triggers a new verification email to be sent to the user's email address if they haven't verified yet.
     * Returns a redirect response with a success message indicating the verification link has been sent.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function resendEmail(Request $request)
    {
        $request->user()->sendEmailVerificationNotification();

        return response()->json([
            'message' => 'Verification email sent successfully',
        ]);
    }
}
