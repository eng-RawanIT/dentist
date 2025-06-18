<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\PatientController;
use App\Http\Controllers\RadiologyController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

Route::controller(AuthController::class)->group(function () {
    Route::post('/addUser', 'AddUser');
    Route::post('/login', 'login');
    Route::post('/logout', 'logout')->middleware('auth:sanctum');
    Route::post('/sendOtp', 'sendOtp');
    Route::post('/verifyOtp', 'verifyOtp');
    Route::post('/patientVerifyOtp', 'patientVerifyOtp');
    Route::post('/resetPassword', 'resetPassword');
    Route::post('/resend-otp', 'resendOtp');
});

Route::controller(PatientController::class)->middleware('auth:sanctum')->group(function () {
    Route::post('/information', 'storeInformation');
    Route::post('/medication-uploadImage', 'uploadMedicationImage');
    Route::post('/diseases', 'storeDiseases');
});

Route::controller(RadiologyController::class)->middleware('auth:sanctum')->group(function () {
    Route::post('/radilogy-uploadImage', 'uploadRadiologyImage');
});
