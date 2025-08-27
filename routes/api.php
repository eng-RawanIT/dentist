<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\AdmissionController;
use App\Http\Controllers\ArchiveController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\PatientController;
use App\Http\Controllers\RadiologyController;
use App\Http\Controllers\ResourceController;
use App\Http\Controllers\StudentController;
use App\Http\Controllers\SupervisorController;
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
    Route::post('/login', 'login');
    Route::post('/sendOtp', 'sendOtp');
    Route::post('/verifyOtp', 'verifyOtp');
    Route::post('/patientVerifyOtp', 'patientVerifyOtp');
    Route::post('/resetPassword', 'resetPassword');
    Route::post('/resend-otp', 'resendOtp');
    Route::middleware(['auth:sanctum'])->group(function () {
        Route::post('/complaint', 'send');
        Route::post('/logout', 'logout');
        Route::post('/verify-edit-Otp', 'verifyEditOtp');
        Route::post('/edit-user-profile', 'editUserProfile');
    });
});

Route::controller(PatientController::class)->middleware(['auth:sanctum', 'role:patient'])->group(function () {
    Route::post('/information', 'storeInformation');
    Route::get('/oral-medicine-dentist', 'oralMedicineDentist');
    Route::get('/requestStatus','requestStatus');
    Route::post('/available-appointment', 'viewAvailableAppointments');
    Route::post('/book-appointment', 'bookAvailableAppointment');
    Route::post('/edit-information', 'updatePatientProfile');
    Route::get('/view-information','viewInformation');
    Route::delete('/delete-medication-image/{id}', 'deleteMedicationImage');
});

Route::controller(StudentController::class)->middleware(['auth:sanctum', 'role:dentalStudent'])->group(function () {
    Route::post('/add-appointment', 'addAvailableAppointment');
    Route::post('/delete-appointment', 'deleteAvailableAppointment');
    Route::post('/change-day-status', 'changeDayStatus');
    Route::get('/appointments', 'viewMyAppointment');
    Route::get('/student-weekly-schedule', 'weeklySchedule');
    Route::post('/previous-info', 'viewPreviousInfo');
    Route::post('/patient-info', 'viewPatientInfo');
    Route::post('/add-description', 'addDescription');
    Route::post('/add-image', 'addTreatmentImage');
    Route::get('/portfolio' , 'showMyPortfolio');
    Route::get('/stages-sessions','getStudentStagesWithSessions');
    Route::get('/QR_code','getStudentQrCodeData');
    Route::post('/educational-contents','listContents');
    Route::post('/show-educational-contents','showContent');
    Route::post('/show-educational-contents-bystage','showEducationalContentByStage');
    Route::get('/portfolio/download','downloadPdf');
    Route::post('/upload-profile-image','uploadProfileImage');
    Route::get('/practical-schedule','practicalSchedule');
    Route::get('/view-emergency-cases','getArchivedSessionsByStage');

});

Route::controller(RadiologyController::class)->middleware(['auth:sanctum', 'role:radiologyManager'])->group(function () {
    Route::post('/radiology-uploadImage', 'uploadRadiologyImage');
    Route::post('/add-patient', 'addPatient');
    Route::post('/search-patient', 'searchPatient');
    Route::get('/all-diseases','allDiseases');
    Route::get('/radiology-stats','radiologyStats');
    Route::get('/recent-images','recentImages');
});

Route::controller(ArchiveController::class)->middleware('auth:sanctum')->group(function () {
    Route::get('/viewArchive', 'viewArchive');
    Route::post('/viewTreatment','viewTreatment');
});

Route::controller(AdmissionController::class)->middleware(['auth:sanctum', 'role:AdmissionManager'])->group(function () {
    Route::get('/admission-weekly-summary', 'weeklySummary');
    Route::get('/patient-request', 'patientRequests');
    Route::get('/all-requests','allPatientRequest');
    Route::get('/stage-dates','stageDates');
    Route::post('/request-details','requestDetails');
    Route::get('/all-stage','allStages');
    Route::post('/assign-patient','sortPatient');
});

Route::controller(AdminController::class)->middleware(['auth:sanctum', 'role:admin'])->group(function () {
    Route::post('/addUser', 'AddUser');
    Route::delete('/delete-user/{id}', 'deleteUser');
    Route::post('/add-schedule','addPracticalSchesdule');
    Route::post('/view-year-schedules','viewYearSchedules');
    Route::get('/view-all-supervisors','viewAllSupervisors');
    Route::get('/view-all-doctors','viewAllDoctors');
    Route::post('/add-stage','addStage');
    Route::get('/view-stages','viewStages');
    Route::delete('/delete-stage/{id}', 'deleteStage');
    Route::get('/statistics', 'statistics');
    Route::get('/today-schedule', 'todaySchedule');
    Route::delete('/delete-schedule/{id}', 'deleteSchedule');
    Route::post('/add-reinternship','addReinternship');
    Route::get('/view-all-reinternships','viewAllReinternships');
    Route::post('/distribute-fourth-year','distributeFourthYearStudents');
    Route::post('/distribute-fifth-year','distributeFifthYearStudents');
});

Route::controller(SupervisorController::class)->middleware(['auth:sanctum', 'role:supervisor'])->group(function () {
    Route::post('/store-educational-content','storeEducationalContent');
    Route::get('/my-educational-contents','myEducationalContents');
    Route::delete('/delete-educational-content/{id}','deleteContent');
    Route::get('/supervisor-weekly-schedule','weeklySchedule');
    Route::post('/practical-schedule-students','getStudentsForPracticalSchedule');
    Route::post('/record-Absences','recordAbsences');
    Route::post('/attendance-Report','downloadAttendanceReport');
    Route::get('/students-Marks' , 'studentsMarks');
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

//for multy auth
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/view-all-patients', [AdminController::class, 'viewAllPatients'])->middleware('role:admin,AdmissionManager');
    Route::post('/patient-information', [AdminController::class,'patientInfo'])->middleware('role:admin,AdmissionManager,RadiologyManager');
    Route::get('/view-all-students', [AdminController::class,'viewAllStudents'])->middleware('role:admin,AdmissionManager');
    Route::post('/supervisor-scan-Qrcode', [SupervisorController::class, 'handleScannedQRCode'])->middleware('role:supervisor,doctor');
    Route::post('/evaluate-session', [SupervisorController::class, 'evaluateSession'])->middleware('role:supervisor,doctor');
});

////DOCROT
Route::controller(SupervisorController::class)->middleware(['auth:sanctum', 'role:doctor'])->group(function () {
    Route::get('/doctorViewCase/{session_id}', 'doctorViewCase');
});

Route::controller(NotificationController::class)->middleware(['auth:sanctum'])->group(function () {
    Route::post('/update-token', 'saveFcmToken');
    Route::get('/notifications', 'index');
    Route::post('/notifications/{notification}/read', 'markAsRead');
    Route::delete('/notifications/clear', 'clearAll');
    Route::delete('/notifications/{notification}','delete');
});

