<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rules\Password;

class AuthController extends Controller
{
    /**
     * Authenticate user with email and password only. Deactivated accounts get 403.
     */
    public function login(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        $email = strtolower(trim($validated['email']));

        $user = User::where('email', $email)->first();

        // Check password against encrypted/hashed password in database
        if (!$user || !Hash::check($validated['password'], $user->password)) {
            return response()->json([
                'message' => 'Invalid email or password credentials.',
                'errors' => [
                    'email' => ['The provided credentials do not match our records.'],
                ],
            ], 422);
        }

        if (!$user->is_active) {
            return response()->json([
                'message' => 'Your account has been deactivated. Please contact an administrator.',
            ], 403);
        }

        // Generate Sanctum access token
        $token = $user->createToken('zcmc_catalogue_token')->plainTextToken;

        return response()->json([
            'message' => 'Login successful.',
            'access_token' => $token,
            'token_type' => 'Bearer',
            'user' => (new UserResource($user->loadMissing('internalCategories')))->resolve(),
        ]);
    }

    /**
     * Send 6-digit password reset verification code via SMTP email.
     */
    public function forgotPassword(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => 'required|email',
        ]);

        $email = strtolower(trim($validated['email']));
        $user = User::where('email', $email)->first();

        if (!$user) {
            return response()->json([
                'message' => 'If an account exists with that email, a password reset code has been sent.',
            ]);
        }

        // Generate 6-digit OTP code
        $otp = sprintf("%06d", mt_rand(100000, 999999));

        // Store hashed OTP in password_reset_tokens table
        DB::table('password_reset_tokens')->updateOrInsert(
            ['email' => $email],
            [
                'token' => Hash::make($otp),
                'created_at' => now(),
            ]
        );

        // Dispatch Password Reset OTP Email asynchronously via queued job
        \App\Jobs\SendPasswordResetOtpJob::dispatch($user, $otp);

        return response()->json([
            'message' => 'A 6-digit password reset code has been sent to your email address.',
        ]);
    }

    /**
     * Reset password using the 6-digit code received via email.
     */
    public function resetPassword(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => 'required|email',
            'code' => 'required|string|size:6',
            'password' => [
                'required',
                'string',
                Password::min(8)
                    ->letters()
                    ->numbers()
                    ->symbols(),
            ],
        ]);

        $email = strtolower(trim($validated['email']));
        $code = trim($validated['code']);

        $record = DB::table('password_reset_tokens')
            ->where('email', $email)
            ->first();

        if (!$record) {
            return response()->json([
                'message' => 'No active password reset request found for this email.',
            ], 422);
        }

        // Check if code has expired (15 minutes limit)
        $createdAt = Carbon::parse($record->created_at);
        if ($createdAt->addMinutes(15)->isPast()) {
            DB::table('password_reset_tokens')->where('email', $email)->delete();
            return response()->json([
                'message' => 'The reset code has expired. Please request a new code.',
            ], 422);
        }

        // Check code against bcrypt hash
        if (!Hash::check($code, $record->token)) {
            return response()->json([
                'message' => 'Invalid verification code. Please check your email and try again.',
            ], 422);
        }

        // Update password
        $user = User::where('email', $email)->first();
        if (!$user) {
            return response()->json([
                'message' => 'User account not found.',
            ], 404);
        }

        $user->password = $validated['password'];
        $user->save();

        // Revoke tokens
        $user->tokens()->delete();

        // Remove token from reset table
        DB::table('password_reset_tokens')->where('email', $email)->delete();

        return response()->json([
            'message' => 'Password has been successfully reset! You can now sign in with your new password.',
        ]);
    }

    /**
     * Logout authenticated user and revoke current access token.
     */
    public function logout(Request $request): JsonResponse
    {
        if ($request->user() && $request->user()->currentAccessToken()) {
            $request->user()->currentAccessToken()->delete();
        }

        return response()->json([
            'message' => 'Logged out successfully.',
        ]);
    }

    /**
     * Change authenticated user's password (e.g. on first-time login or profile update).
     */
    public function changePassword(Request $request): JsonResponse
    {
        $user = $request->user();

        $rules = [
            'password' => [
                'required',
                'string',
                'confirmed',
                Password::min(8)
                    ->numbers()
                    ->symbols(),
            ],
        ];

        // Require current_password only if user is not in mandatory first-time setup
        if (!$user->must_change_password || $request->filled('current_password')) {
            $rules['current_password'] = 'required|string';
            $rules['password'][] = 'different:current_password';
        }

        $validated = $request->validate($rules, [
            'password.different' => 'Your new password cannot be the same as your current password.',
        ]);

        if (isset($validated['current_password'])) {
            if (!Hash::check($validated['current_password'], $user->password)) {
                return response()->json([
                    'message' => 'The provided current password does not match our records.',
                    'errors' => [
                        'current_password' => ['The provided current password does not match our records.'],
                    ],
                ], 422);
            }
        }

        // Prevent setting the exact same password as the current / initial password
        if (Hash::check($validated['password'], $user->password)) {
            return response()->json([
                'message' => 'Your new permanent password cannot be the same as your initial temporary password.',
                'errors' => [
                    'password' => ['Your new permanent password cannot be the same as your initial temporary password.'],
                ],
            ], 422);
        }

        $user->password = $validated['password'];
        $user->must_change_password = false;
        $user->save();

        \App\Models\ActivityLog::record(
            'PASSWORD_CHANGED',
            "User changed password: {$user->name} ({$user->email})",
            'User',
            $user->id,
            $user->email
        );

        return response()->json([
            'message' => 'Your password has been changed successfully.',
            'user' => (new UserResource($user->loadMissing('internalCategories')))->resolve(),
        ]);
    }

    /**
     * Retrieve authenticated user profile.
     */
    public function me(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'user' => (new UserResource($user->loadMissing('internalCategories')))->resolve(),
        ]);
    }
}

