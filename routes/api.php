<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\SalonController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Public routes — no login required
Route::post('/auth/send-otp', [AuthController::class, 'sendOtp']);
Route::post('/auth/verify-otp', [AuthController::class, 'verifyOtp']);

// Protected routes — must have a valid token
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', function (Request $request) {
        return $request->user();
    });

    Route::get('/auth/me', [AuthController::class, 'me']);

    // Salons — anyone logged in can view/list, but only salon_owner can create
    Route::get('/salons', [SalonController::class, 'index']);
    Route::get('/salons/{salon}', [SalonController::class, 'show']);

    Route::middleware('role:salon_owner,super_admin')->group(function () {
        Route::post('/salons', [SalonController::class, 'store']);
        Route::put('/salons/{salon}', [SalonController::class, 'update']);
        Route::delete('/salons/{salon}', [SalonController::class, 'destroy']);
    });
});