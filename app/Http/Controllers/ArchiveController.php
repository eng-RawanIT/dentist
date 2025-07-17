<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\Patient;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ArchiveController extends Controller
{

    // patient  archive section
    public function viewArchive()
    {
        $patient = Patient::where('user_id', Auth::id())
            ->with(['appointments.student.user', 'appointments.stage', 'appointments.session']) // eager load sessions
            ->firstOrFail();

        $studentsPerStage = $patient->appointments
            ->groupBy(function ($appointment) {
                return $appointment->student_id . '_' . $appointment->stage_id;
            })
            ->map(function ($group) {
                $first = $group->first();
                $student = $first->student;
                $stage = $first->stage;

                $avgEvaluation = $group->pluck('session')
                    ->filter() // remove null sessions
                    ->pluck('evaluation_score')
                    ->avg();

                return [
                    'student_id' => $student->id,
                    'name' => $student->user->name,
                    'year' => $student->year,
                    'stage_name' => $stage->name,
                    'profile_image' => $student->profile_image_url,
                    'stage_id' => $stage->id,
                    'average_evaluation' => $avgEvaluation ? round($avgEvaluation, 2) : null,
                ];
            })
            ->values();

        return response()->json([
            'status' => 'success',
            'students' => $studentsPerStage
        ]);
    }

    public function viewTreatment(Request $request)
    {
        $request->validate([
            'student_id' => 'required|exists:students,id',
            'stage_id' => 'required|exists:stages,id',
        ]);

        $patient = Patient::where('user_id', Auth::id())->firstOrFail();

        // Get the latest processed request for the stage
        $treatmentRequest = $patient->patientRequests()
            ->where('stage_id', $request->stage_id)
            ->where('status', 'processed')
            ->latest()
            ->first();

        if (!$treatmentRequest) {
            return response()->json([
                'status' => 'no_data',
                'message' => 'No processed treatment request found for this stage.'
            ]);
        }

        // Get appointments for this request, student, and stage
        $appointments = Appointment::where('request_id', $treatmentRequest->id)
            ->where('student_id', $request->student_id)
            ->with('session.images')
            ->orderBy('date')
            ->get();

        if ($appointments->isEmpty()) {
            return response()->json([
                'status' => 'no_data',
                'message' => 'No appointments found for this student and stage under this request.'
            ]);
        }

        // Get the first session
        $firstSession = $appointments->pluck('session')->filter()->first();

        // All sessions
        $allSessions = $appointments->pluck('session')->filter();

        // Radiology images from the parent request
        $radiologyImages = $treatmentRequest->radiologyImages->map(function ($image) {
            return [
                'url' => asset('storage/' . $image->image_url),
            ];
        });

        // Appointment list with status
        $appointmentDetails = $appointments->map(function ($appointment) {
            return [
                'date' => $appointment->date,
                'time' => Carbon::createFromFormat('H:i:s', $appointment->time)->format('g A'),
                'isDone' => $appointment->session ? 'true' : 'false',
            ];
        });

        // Before and after session images
        $beforeImages = $allSessions->flatMap(function ($session) {
            return $session->images->where('type', 'before-treatment')->map(function ($img) {
                return [
                    'url' => asset('storage/' . $img->image_url),
                    'type' => 'before'
                ];
            });
        })->values();

       $afterImages = $allSessions->flatMap(function ($session) {
            return $session->images->where('type', 'after-treatment')->map(function ($img) {
                return [
                    'url' => asset('storage/' . $img->image_url),
                    'type' => 'after'
                ];
            });
        })->values();

        return response()->json([
            'status' => 'success',
            'session_description' => optional($firstSession)->description,
            'appointment_dates' => $appointmentDetails,
            'radiology_images' => $radiologyImages,
            'before_images' => $beforeImages,
            'after_images' => $afterImages,
        ]);
    }

    /*public function viewTreatment(Request $request){

        $request->validate([
            'student_id' => 'required|exists:students,id',
            'stage_id' => 'required|exists:stages,id',
        ]);

        $patient = Patient::where('user_id', Auth::id())->firstOrFail();

        // Get all appointments for this student & stage
        $appointments = $patient->appointments()
            ->where('student_id', $request->student_id)
            ->where('stage_id', $request->stage_id)
            ->with('session.images')
            ->orderBy('date')
            ->get();

        if ($appointments->isEmpty()) {
            return response()->json([
                'status' => 'no_data',
                'message' => 'No appointments found for this student and stage.'
            ]);
        }

        // Get the first appointment that has a session
        $firstSession = $appointments->pluck('session')->filter()->first();

        // Collect all session images
        $allSessions = $appointments->pluck('session')->filter();

        $beforeImages = $allSessions->flatMap(function ($session) {
            return $session->images->where('type', 'before')->map(function ($img) {
                return ['url' => $img->image_url, 'type' => 'before'];
            });
        })->values();

        $afterImages = $allSessions->flatMap(function ($session) {
            return $session->images->where('type', 'after')->map(function ($img) {
                return ['url' => $img->image_url, 'type' => 'after'];
            });
        })->values();

        // Collect appointment dates
        $appointmentDates = $appointments->pluck('date')->unique()->values();

        return response()->json([
            'status' => 'success',
            'session_description' => optional($firstSession)->description,
            'appointment_dates' => $appointmentDates,
            'before_images' => $beforeImages,
            'after_images' => $afterImages,
        ]);
    }*/
}
