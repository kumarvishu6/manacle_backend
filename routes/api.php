<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BookingController;
use App\Http\Controllers\Api\ChairController;
use App\Http\Controllers\Api\SalonController;
use App\Http\Controllers\Api\ServiceController;
use App\Http\Controllers\Api\StaffController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::post('/auth/send-otp', [AuthController::class, 'sendOtp']);
Route::post('/auth/verify-otp', [AuthController::class, 'verifyOtp']);

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', function (Request $request) {
        return $request->user();
    });

    Route::get('/auth/me', [AuthController::class, 'me']);
    Route::post('/auth/logout', [AuthController::class, 'logout']);

    Route::get('/salons', [SalonController::class, 'index']);
    Route::get('/salons/{salon}', [SalonController::class, 'show']);
    Route::get('/salons/{salon}/wait-preview', [SalonController::class, 'waitPreview']);
    Route::get('/salons/{salon}/chairs', [ChairController::class, 'index']);
    Route::get('/salons/{salon}/services', [ServiceController::class, 'index']);

    Route::get('/bookings/mine/active', [BookingController::class, 'myActive']);
    Route::post('/salons/{salon}/bookings', [BookingController::class, 'store']);
    Route::get('/salons/{salon}/bookings', [BookingController::class, 'index']);
    Route::post('/salons/{salon}/walk-ins', [BookingController::class, 'walkIn']);
    Route::get('/bookings/{booking}', [BookingController::class, 'show']);
    Route::post('/bookings/{booking}/start', [BookingController::class, 'start']);
    Route::post('/bookings/{booking}/complete', [BookingController::class, 'complete']);
    Route::post('/bookings/{booking}/no-show', [BookingController::class, 'noShow']);
    Route::post('/bookings/{booking}/cancel', [BookingController::class, 'cancel']);

    Route::middleware('role:salon_owner,super_admin')->group(function () {
        Route::post('/salons', [SalonController::class, 'store']);
        Route::put('/salons/{salon}', [SalonController::class, 'update']);
        Route::delete('/salons/{salon}', [SalonController::class, 'destroy']);

        Route::post('/salons/{salon}/chairs', [ChairController::class, 'store']);
        Route::put('/chairs/{chair}', [ChairController::class, 'update']);
        Route::delete('/chairs/{chair}', [ChairController::class, 'destroy']);

        Route::post('/salons/{salon}/services', [ServiceController::class, 'store']);
        Route::put('/services/{service}', [ServiceController::class, 'update']);
        Route::delete('/services/{service}', [ServiceController::class, 'destroy']);

        Route::get('/salons/{salon}/staff', [StaffController::class, 'index']);
        Route::post('/salons/{salon}/staff', [StaffController::class, 'store']);
        Route::delete('/staff/{staff}', [StaffController::class, 'destroy']);
    });
});