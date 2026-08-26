<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Validator;

class AuthController extends Controller
{
    /**
     * Step 1: Customer/owner enters phone number, we generate and "send" an OTP.
     */
    public function sendOtp(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'phone' => 'required|string|min:10|max:15',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $rateLimitKey = 'send-otp:' . $request->phone;

        if (RateLimiter::tooManyAttempts($rateLimitKey, 5)) {
            $seconds = RateLimiter::availableIn($rateLimitKey);
            return response()->json([
                'message' => "Too many OTP requests. Try again in {$seconds} seconds.",
            ], 429);
        }

        RateLimiter::hit($rateLimitKey, 600); // 5 attempts per 10 minutes

        $otp = rand(100000, 999999);

        $user = User::firstOrCreate(
            ['phone' => $request->phone],
            ['name' => 'User', 'role' => 'customer']
        );

        $user->otp_code = $otp;
        $user->otp_expires_at = now()->addMinutes(5);
        $user->otp_attempts = 0;
        $user->save();

        // TODO: integrate real SMS provider (MSG91/Twilio/2Factor) here later.
        $response = [
            'message' => 'OTP sent successfully',
        ];

        if (config('app.show_dev_otp')) {
            $response['dev_otp'] = $otp;
        }

        return response()->json($response);
    }

    /**
     * Step 2: User submits phone + OTP, we verify and issue an API token.
     */
    public function verifyOtp(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'phone' => 'required|string',
            'otp' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $user = User::where('phone', $request->phone)->first();

        if (! $user) {
            return response()->json(['message' => 'User not found'], 404);
        }

        if (($user->otp_attempts ?? 0) >= 5) {
            return response()->json([
                'message' => 'Too many incorrect attempts. Please request a new OTP.',
            ], 429);
        }

        if ($user->otp_code !== $request->otp) {
            $user->increment('otp_attempts');
            return response()->json(['message' => 'Invalid OTP'], 401);
        }

        if (now()->greaterThan($user->otp_expires_at)) {
            return response()->json(['message' => 'OTP expired'], 401);
        }

        // OTP correct — clear it, mark verified, issue token
        $user->otp_code = null;
        $user->otp_expires_at = null;
        $user->otp_attempts = 0;
        $user->phone_verified_at = now();
        $user->save();

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'message' => 'Login successful',
            'token' => $token,
            'user' => $user,
        ]);
    }

    /**
     * Get the currently logged-in user (used to test if a token works).
     */
    public function me(Request $request)
    {
        return response()->json($request->user());
    }
}