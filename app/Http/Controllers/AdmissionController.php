<?php

namespace App\Http\Controllers;

use App\Models\Patient;
use App\Models\PatientRequest;
use App\Models\PracticalSchedule;
use App\Models\Stage;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class AdmissionController extends Controller
{
    // احصائيات اسبوعية لكل ستاج بنحسب كم جلسة انعمله وشو المتوسط الحسابي للتقييمات
    public function weeklySummary()
    {
        // 1. Define the custom week range (Sunday to Thursday)
        $startOfWeek = now()->startOfWeek(Carbon::SUNDAY);
        $endOfWeek = $startOfWeek->copy()->addDays(4);

        // 2. Get distinct stage IDs from practical_schedules to avoid duplicates
        $validStageIds = DB::table('practical_schedules')
            ->distinct()
            ->pluck('stage_id');

        // 3. Query the session statistics
        $stats = DB::table('sessions')
            ->join('appointments', 'sessions.appointment_id', '=', 'appointments.id')
            ->join('stages', 'appointments.stage_id', '=', 'stages.id')
            ->select([
                'appointments.stage_id',
                'stages.name as stage_name',
                DB::raw('COUNT(sessions.id) as session_count'),
                DB::raw('AVG(sessions.evaluation_score) as average_score')
            ])
            ->whereBetween('sessions.date', [$startOfWeek, $endOfWeek])
            ->whereIn('appointments.stage_id', $validStageIds)
            ->groupBy('appointments.stage_id', 'stages.name')
            ->orderBy('stages.name')
            ->get();

        // 4. Format the results
        $formattedStats = $stats->map(function ($item) {
            return [
                'stage_id' => $item->stage_id,
                'stage_name' => $item->stage_name,
                'session_count' => (int)$item->session_count,
                'average_score' => round((float)$item->average_score, 2)
            ];
        });

        return response()->json([
            'status' => 'success',
            'data' => $formattedStats
        ]);
    }

    public function patientRequest()
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
        $sessions = PracticalSchedule::select('stage_id', 'days', 'start_time')
            ->get()
            ->unique('stage_id'); // Collection method

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
        $stages = PracticalSchedule::select('stage_id')
            ->get()
            ->unique('stage_id'); // Collection method

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

        return response()->json([
            'status' => 'success',
            'message' => 'patient request processed successfully'
        ]);
    }
}
