<?php

namespace App\Http\Controllers;

use App\Models\Patient;
use App\Models\PatientRequest;
use App\Models\PracticalSchedule;
use App\Models\Session;
use App\Models\Stage;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class AdmissionController extends Controller
{
    public function __construct(private NotificationService $notificationService) {}

    // احصائيات اسبوعية لكل ستاج بنحسب كم جلسة انعمله وشو المتوسط الحسابي للتقييمات
    public function weeklySummary()
    {
        // 1. Define the custom week range (Sunday to Thursday)
        $startOfWeek = now()->startOfWeek(Carbon::SUNDAY);
        $endOfWeek = $startOfWeek->copy()->addDays(4);

        $sessions = Session::with(['appointment.request'])
            ->join('appointments', 'sessions.appointment_id', '=', 'appointments.id')
            ->whereBetween('appointments.date', [$startOfWeek, $endOfWeek])
            ->get();

        // Group by stage_id (from request)
        $grouped = $sessions->groupBy(function ($session) {
            return $session->appointment->request->stage_id;
        });

        $result = $grouped->map(function ($sessions, $stageId) {
            $stageName = Stage::find($stageId)->name['en'];
            $averageScore = $sessions->pluck('evaluation_score')->filter()->avg();
            return [
                'stage_id' => $stageId,
                'stage_name' => $stageName,
                'session_count' => $sessions->count(),
                'average_score' => $averageScore ? round($averageScore, 2) : null
            ];
        })->values();

        return response()->json([
            'status' => 'success',
            'data' => $result
        ]);
    }

    public function patientRequests()
    {
        $requests = PatientRequest::where('status', 'under processing')->paginate(5);;
        return response()->json([
            'status' => 'success',
            'requests' => $requests
        ]);
    }

    public function allPatientRequest()
    {
        return PatientRequest::paginate(10);
    }

    public function stageDates()
    {
        $sessions = PracticalSchedule::all()->map(function ($s) {
            return [
                'stage_id' => $s->stage_id,
                'name' => Stage::find($s->id)->name['en'],
                'days' => $s->days,
                'start_time' => $s->start_time
            ];
        })->unique('stage_id');

        return response()->json([
            'status' => 'success',
            'sessions' => $sessions
        ]);
    }

    public function requestDetails(Request $request)
    {
        $request->validate([
            'request_id' => 'required|exists:requests,id',
        ]);

        $req = PatientRequest::where('id',$request->request_id)->first();
        $patient = Patient::where('id',$req->patient_id)->first();
        $user = User::where('id',$patient->user_id)->first();

        // Calculate patient's age from birthdate
        $age = $patient->birthdate ? Carbon::parse($patient->birthdate)->age : null;

        $radiologyImages = $patient->radiologyImages->map(fn($image) => [
            'url' => asset('storage/' . $image->image_url),
            'type' => $image->type,
        ]);

        return response()->json([
            'status' => 'success',
            'patient' => [
                'name' => $user->name,
                'phone' => $user->phone_number,
                'age' => $age,
                'height' => $patient->height,
                'weight' => $patient->weight,
                'birthdate' => $patient->birthdate,
            ],
            'radiology_images' => $radiologyImages,
        ]);
    }

    public function allStages()
    {
        $stages = PracticalSchedule::all()->map(function ($s) {
            return [
                'stage_id' => $s->stage_id,
                'name' => Stage::find($s->id)->name['en'],
            ];
        })->unique('stage_id');

        return response()->json([
            'status' => 'success',
            'sessions' => $stages
        ]);
    }

    public function sortPatient(Request $request)
    {
        $request->validate([
            'request_id' => 'required|exists:requests,id',
            'stage_id' => 'required|exists:stages,id'
        ]);

        $patientRequest = PatientRequest::findOrFail($request->request_id);

        if ($patientRequest->status === 'processed') {
            return response()->json([
                'status' => 'error',
                'message' => 'This request has already been processed and cannot be assigned.'
            ], 400);
        }

        $patientRequest->update([
                'stage_id' => $request->stage_id,
                'status' => 'processed'
            ]);

        $patient = Patient::find($patientRequest->patient_id);
        $this->notificationService->notifyUser(
            $patient->user->id ,
            "تم معالجة طلبك",
            "تم معالجة طلبك بنجاح , بامكانك البدء باختيار الطبيب المناسب للبدء بعلاجك معه",
        );

        return response()->json([
            'status' => 'success',
            'message' => 'patient request processed successfully'
        ]);
    }
}
