<?php

namespace App\Services;

use App\Models\User;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;

define('WAIT_TIME', '-5 minutes');

class RegistrationService
{
    public function register($registrationData): JsonResponse
    {
        $user = User::create($registrationData);

        // Create a token for the user
        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'message' => 'User created successfully',
            'token' => $token,
        ]);
    }

    public function login($email, $password): JsonResponse
    {
        $user = $this->getUserByEmail($email);

        // Check if user exists
        if (! $user) {
            return response()->json([
                'message' => 'The provided credentials are incorrect.',
                'errors' => [
                    'email' => ['The provided credentials are incorrect.'],
                ],
            ]);
        }

        // Check if password is correct
        if (! $this->checkIfPasswordIsCorrect($password, $user->password)) {
            return response()->json([
                'message' => 'The provided password is incorrect.',
                'errors' => [
                    'password' => ['The provided password is incorrect.'],
                ],
            ]);
        }

        // Create a token for the user
        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'message' => 'Login successful',
            'token' => $token,
        ]);
    }

    public function logout(): JsonResponse
    {
        $user = auth()->user();

        // Revoke the token of the user
        $user->currentAccessToken()->delete();

        return response()->json([
            'message' => 'Logout successful',
        ]);
    }

    /**
     * @throws Exception
     */
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

        $passwordResetToken = DB::table('password_reset_tokens')->where('email', $email)->first();

        // Check if password reset token exists
        if ($passwordResetToken) {
            // Check if password reset token is expired
            if (strtotime($passwordResetToken->created_at) > strtotime(WAIT_TIME)) {
                throw new Exception('You must wait before requesting to reset your password again.', 429);
            }
        }

        // Make a token with 6-digit numbers
        $token = $this->createResetToken($email);

        // Send the token to the user via email
        $this->sendResetToken($email, $token);

        return response()->json([
            'message' => 'We have sent a code to your email address.',
        ]);
    }

    /**
     * @throws Exception
     */
    public function resetPassword($email, $token, $newPassword): JsonResponse
    {
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

    public function sendResetToken($email, $token): void
    {
        Mail::send('forgot-password', ['token' => $token], function ($message) use ($email) {
            $message->to($email);
            $message->subject('Reset Password Notification');
        });
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
        if (strtotime($passwordResetToken->created_at) < strtotime($increasedExpiration ? '-30 minutes' : WAIT_TIME)) {
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
