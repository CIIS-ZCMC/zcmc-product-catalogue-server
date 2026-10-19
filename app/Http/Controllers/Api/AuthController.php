<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\SendPasswordResetOtpJob;
use App\Jobs\SendWelcomeEmailJob;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class AuthController extends Controller
{
    /**
     * Authenticate user with email and password only.
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
        if (! $user || ! Hash::check($validated['password'], $user->password)) {
            return response()->json([
                'message' => 'Invalid email or password credentials.',
                'errors' => [
                    'email' => ['The provided credentials do not match our records.'],
                ],
            ], 422);
        }

        // Generate Sanctum access token
        $token = $user->createToken('zcmc_catalogue_token')->plainTextToken;

        return response()->json([
            'message' => 'Login successful.',
            'access_token' => $token,
            'token_type' => 'Bearer',
            'user' => [
                'id' => $user->id,
                'first_name' => $user->first_name,
                'last_name' => $user->last_name,
                'name' => $user->name,
                'email' => $user->email,
                'is_admin' => (bool) $user->is_admin,
                'created_at' => $user->created_at,
            ],
        ]);
    }

    /**
     * Register a new user with first name, last name, email, and strong password.
     */
    public function register(Request $request): JsonResponse
    {
        // Support either first_name/last_name or combined name input
        if (! $request->filled('first_name') && $request->filled('name')) {
            $parts = explode(' ', trim($request->input('name')), 2);
            $request->merge([
                'first_name' => $parts[0] ?: 'User',
                'last_name' => $parts[1] ?? '',
            ]);
        }

        $passwordRules = [
            'required',
            'string',
            Password::min(8)
                ->letters()
                ->numbers()
                ->symbols(),
        ];

        if ($request->has('password_confirmation')) {
            $passwordRules[] = 'confirmed';
        }

        $validated = $request->validate([
            'first_name' => 'required|string|max:100',
            'last_name' => 'nullable|string|max:100',
            'email' => 'required|email|max:255|unique:users,email',
            'password' => $passwordRules,
        ]);

        $firstName = trim($validated['first_name']);
        $lastName = trim($validated['last_name'] ?? '');
        $email = strtolower(trim($validated['email']));

        // Create user with first_name, last_name, and securely hashed password
        $user = User::create([
            'first_name' => $firstName,
            'last_name' => $lastName,
            'email' => $email,
            'password' => $validated['password'],
        ]);

        // Dispatch Welcome Email asynchronously via queued job
        SendWelcomeEmailJob::dispatch($user);

        // Generate Sanctum access token for immediate authentication
        $token = $user->createToken('zcmc_catalogue_token')->plainTextToken;

        return response()->json([
            'message' => 'User registered and authenticated successfully.',
            'access_token' => $token,
            'token_type' => 'Bearer',
            'user' => [
                'id' => $user->id,
                'first_name' => $user->first_name,
                'last_name' => $user->last_name,
                'name' => $user->name,
                'email' => $user->email,
                'is_admin' => (bool) $user->is_admin,
                'created_at' => $user->created_at,
            ],
        ], 201);
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

        if (! $user) {
            return response()->json([
                'message' => 'If an account exists with that email, a password reset code has been sent.',
            ]);
        }

        // Generate 6-digit OTP code
        $otp = sprintf('%06d', mt_rand(100000, 999999));

        // Store hashed OTP in password_reset_tokens table
        DB::table('password_reset_tokens')->updateOrInsert(
            ['email' => $email],
            [
                'token' => Hash::make($otp),
                'created_at' => now(),
            ]
        );

        // Dispatch Password Reset OTP Email asynchronously via queued job
        SendPasswordResetOtpJob::dispatch($user, $otp);

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

        if (! $record) {
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
        if (! Hash::check($code, $record->token)) {
            return response()->json([
                'message' => 'Invalid verification code. Please check your email and try again.',
            ], 422);
        }

        // Update password
        $user = User::where('email', $email)->first();
        if (! $user) {
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
     * Retrieve authenticated user profile.
     */
    public function me(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'user' => [
                'id' => $user->id,
                'first_name' => $user->first_name,
                'last_name' => $user->last_name,
                'name' => $user->name,
                'email' => $user->email,
                'is_admin' => (bool) $user->is_admin,
                'created_at' => $user->created_at,
            ],
        ]);
    }
}
