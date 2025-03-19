<?php

namespace App\Services;

use App\Http\Resources\UserResource;
use App\Models\User;
use Exception;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;

define('WAIT_TIME', '-5 minutes');
define('INCREASED_WAIT_TIME', '-30 minutes');
define('WAIT_TIME_IN_SECONDS', 300);
define('MAX_REGISTER_ATTEMPTS', 1);
define('MAX_LOGIN_ATTEMPTS', 5);
define('MAX_FORGOT_ATTEMPTS', 1);

class RegistrationService
{
    protected MailService $mailService;

    public function __construct(MailService $mailService)
    {
        $this->mailService = $mailService;
    }

    public function register($registrationData): JsonResponse
    {
        $email = $registrationData['email'];

        // Check if the user has reached the maximum number of attempts
        if (RateLimiter::tooManyAttempts('register:'.$email, MAX_REGISTER_ATTEMPTS)) {
            return response()->json([
                'message' => 'Too many attempts. Please try again later.',
            ], 429);
        }

        // Increment the rate limiter for the register
        RateLimiter::increment('register:'.$email, WAIT_TIME_IN_SECONDS);

        // Create a new user
        $user = User::create($registrationData);

        event(new Registered($user));

        // Create a token for the user
        $token = $user->createToken('auth_token')->plainTextToken;

        // Clear the rate limiter for the register
        RateLimiter::clear('register:'.$email);

        return response()->json([
            'message' => 'User created successfully',
            'token' => $token,
            'user' => new UserResource($user),
        ]);
    }

    public function login($email, $password): JsonResponse
    {
        // Check if the user has reached the maximum number of attempts
        if (RateLimiter::tooManyAttempts('login:'.$email, MAX_LOGIN_ATTEMPTS)) {
            return response()->json([
                'message' => 'Too many attempts. Please try again later.',
            ], 429);
        }

        // Increase the rate limiter for the login
        RateLimiter::increment('login:'.$email, WAIT_TIME_IN_SECONDS);

        $user = $this->getUserByEmail($email);

        // Check if user exists
        if (! $user) {
            return response()->json([
                'message' => 'The provided credentials are incorrect.',
                'errors' => [
                    'email' => ['The provided credentials are incorrect.'],
                ],
            ], 401);
        }

        // Check if password is correct
        if (! $this->checkIfPasswordIsCorrect($password, $user->password)) {
            return response()->json([
                'message' => 'The provided password is incorrect.',
                'errors' => [
                    'password' => ['The provided password is incorrect.'],
                ],
            ], 401);
        }

        // Create a token for the user
        $token = $user->createToken('auth_token')->plainTextToken;

        // Clear the rate limiter for the login
        RateLimiter::clear('login:'.$email);

        return response()->json([
            'message' => 'Login successful',
            'token' => $token,
            'user' => new UserResource($user),
        ]);
    }

    public function logout(): void
    {
        $user = auth()->user();

        // Revoke the token of the user
        $user->currentAccessToken()->delete();
    }

    public function forgotPassword($email): JsonResponse
    {
        $user = $this->getUserByEmail($email);

        // Check if user exists
        if (! $user) {
            return response()->json([
                'message' => 'No user found with this email address.',
                'errors' => [
                    'email' => ['No user found with this email address.'],
                ],
            ], 404);
        }

        // Check if password reset token exists
        //        $passwordResetToken = DB::table('password_reset_tokens')->where('email', $email)->first();
        //        if ($passwordResetToken) {
        //            // Check if password reset token is expired
        //            if (strtotime($passwordResetToken->created_at) > strtotime(WAIT_TIME)) {
        //                return response()->json([
        //                    'message' => 'You must wait before requesting to reset your password again.',
        //                ], 429);
        //            }
        //        }

        // Check if the user has reached the maximum number of attempts
        if (RateLimiter::tooManyAttempts('forgot-password:'.$email, MAX_FORGOT_ATTEMPTS)) {
            $seconds = RateLimiter::availableIn('forgot-password:'.$email);
            $minutes = ceil($seconds / 60);

            return response()->json([
                'message' => 'You may try again in '.$minutes.' '.($minutes == 1 ? 'minute' : 'minutes').'.',
            ], 429);
        }

        // Throttle the user from requesting to reset the password
        RateLimiter::increment('forgot-password:'.$email, WAIT_TIME_IN_SECONDS);

        // Make a token with 6-digit numbers
        $token = $this->createResetToken($email);

        // Send the token to the user via email
        $this->mailService->sendResetToken($email, $token);

        return response()->json([
            'message' => 'We have sent a code to your email address.',
        ]);
    }

    /**
     * @throws Exception
     */
    public function resetPassword($email, $token, $newPassword): JsonResponse
    {
        // Clear the rate limiter for the forgot password
        RateLimiter::clear('forgot-password:'.$email);

        // Validate the password reset token first
        $this->validatePasswordToken($email, $token, true);

        $user = $this->getUserByEmail($email);

        // Get the user with the email
        if (! $user) {
            return response()->json([
                'message' => 'We can\'t find a user with that e-mail address.',
            ], 404);
        }

        // Check if old password and new password is same
        if (Hash::check($newPassword, $user->password)) {
            return response()->json([
                'message' => 'New password cannot be same as your current password. Please choose a different password.',
                'errors' => [
                    'new_password' => ['New password cannot be same as your current password. Please choose a different password.'],
                ],
            ], 422);
        }

        $user->update([
            'password' => Hash::make($newPassword),
        ]);

        // Delete the password reset token
        DB::table('password_reset_tokens')->where('email', $user->email)->delete();

        return response()->json([
            'message' => 'Password reset successfully',
        ]);
    }

    public function createResetToken($email): int
    {
        $token = rand(100000, 999999);
        //        $token = random_int(100000, 999999);

        // Delete any existing password reset token
        DB::table('password_reset_tokens')->where('email', $email)->delete();

        // Create a new password reset token for the user
        DB::table('password_reset_tokens')->insert([
            'email' => $email,
            'token' => Hash::make($token),
            'created_at' => now(),
        ]);

        return $token;
    }

    /**
     * @throws Exception
     */
    public function validatePasswordToken($email, $token, $increasedExpiration = false): object
    {
        $passwordResetToken = DB::table('password_reset_tokens')
            ->where('email', $email)
            ->first();

        // Check if password reset token exists and is valid
        if (! $passwordResetToken || ! Hash::check($token, $passwordResetToken->token)) {
            throw new Exception('This password reset token is invalid.', 404);
        }

        // Check if password reset token is expired
        if (strtotime($passwordResetToken->created_at) < strtotime($increasedExpiration ? INCREASED_WAIT_TIME : WAIT_TIME)) {
            throw new Exception('This password reset token is expired.', 404);
        }

        return response()->json([
            'message' => 'Password reset token is valid.',
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
