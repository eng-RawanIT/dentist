<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\AdmissionController;
use App\Http\Controllers\ArchiveController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\PatientController;
use App\Http\Controllers\RadiologyController;
use App\Http\Controllers\ResourceController;
use App\Http\Controllers\StudentController;
use App\Http\Controllers\SupervisorController;
use App\Models\Resources;
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

//for both middleware role permission :  ->middleware(['auth:sanctum', 'role:patient,dentalStudent'])
Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

Route::controller(AuthController::class)->group(function () {
    Route::get('/sms', 'sms');
    Route::post('/addUser', 'AddUser');
    Route::post('/login', 'login');
    Route::post('/logout', 'logout')->middleware('auth:sanctum');
    Route::post('/sendOtp', 'sendOtp');
    Route::post('/verifyOtp', 'verifyOtp');
    Route::post('/patientVerifyOtp', 'patientVerifyOtp');
    Route::post('/resetPassword', 'resetPassword');
    Route::post('/resend-otp', 'resendOtp');
    Route::post('/editProfile', 'updateProfile')->middleware('auth:sanctum');
});

Route::controller(PatientController::class)->middleware(['auth:sanctum', 'role:patient'])->group(function () {
    Route::post('/information', 'storeInformation');
    Route::get('/oral-medicine-dentist', 'oralMedicineDentist');
    Route::get('/requestStatus','requestStatus');
    Route::post('/available-appointment', 'viewAvailableAppointments');
    Route::post('/book-appointment', 'bookAvailableAppointment');
});

Route::controller(StudentController::class)->middleware(['auth:sanctum', 'role:dentalStudent'])->group(function () {
    Route::post('/add-appointment', 'addAvailableAppointment');
    Route::post('/delete-appointment', 'deleteAvailableAppointment');
    Route::post('/change-day-status', 'changeDayStatus');
    Route::get('/appointments', 'viewMyAppointment');
    Route::get('/weekly-schedule', 'weeklySchedule');
    Route::post('/radiology-images', 'viewRadiologyImages');
    Route::post('/patient-info', 'viewPatientInfo');
    Route::post('/session-information', 'sessionInformation');
    Route::post('/add-appointment-to-patient', 'addAppointmentToPatient');
    Route::get('/portfolio' , 'showMyPortfolio');
    Route::get('/stages-sessions','getStudentStagesWithSessions');
    Route::get('/QR_code','getStudentQrCodeData');
    Route::post('/educational-contents','listContents');
    Route::post('/show-educational-contents','showContent');
    Route::post('/show-educational-contents-bystage','showEducationalContentByStage');
    Route::get('/portfolio/download','downloadPdf');
});

Route::controller(RadiologyController::class)->middleware(['auth:sanctum', 'role:radiologyManager'])->group(function () {
    Route::post('/radilogy-uploadImage', 'uploadRadiologyImage');
});

Route::controller(ArchiveController::class)->middleware('auth:sanctum')->group(function () {
    Route::get('/viewArchive', 'viewArchive');
    Route::post('/viewTreatment','viewTreatment');
});

Route::controller(AdmissionController::class)->middleware(['auth:sanctum', 'role:AdmissionManager'])->group(function () {
    Route::get('/weekly-summary', 'weeklySummary');
    Route::get('/patient-request', 'patientRequest');
    Route::get('/all-requests','allPatientRequest');
    Route::get('/stage-dates','stageDates');
    Route::post('/request-details','requestDetails');
    Route::get('/all-stage','allStages');
    Route::post('/assign-patient','sortPatient');
});

Route::controller(AdminController::class)->middleware(['auth:sanctum', 'role:admin'])->group(function () {
    Route::post('/add-schedule','addPracticalSchesdule');
    Route::post('/view-year-schedules','viewYearSchedules');
});

Route::controller(SupervisorController::class)->middleware(['auth:sanctum', 'role:supervisor'])->group(function () {
    Route::post('/store-educational-content','storeEducationalContent');
    Route::get('/my-educational-contents','myEducationalContents');
    Route::delete('/delete-educational-content/{id}','deleteContent');
});

Route::controller(ResourceController::class)->middleware(['auth:sanctum', 'role:dentalStudent'])->group(function () {
    Route::post('/add-resource','addResource');
    Route::get('/myResources','showMyResources');
    Route::get('/myRequestedResources','showRequestedResources');
    Route::get('/resourceDetails/{id}','showResourceDetails');
    Route::post('/showResourcesByCategory','showResourcesByCategory');
    Route::post('/bookResource','bookResource');
    Route::post('/releaseResource','releaseResource');



});
