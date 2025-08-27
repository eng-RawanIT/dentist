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
    public function viewArchive(Request $request)
    {
        // for translation
        $lang = $request->header('Accept-Language', app()->getLocale());

        $patient = Patient::where('user_id', Auth::id())
            ->with([
                'patientRequests.stage',
                'patientRequests.appointments' => function($query) {
                    $query->with([
                        'student.user',
                        'session'
                    ]);
                }
            ])
            ->firstOrFail();

        // Process the data to maintain same output structure
        $studentsPerStage = $patient->patientRequests
            // Flatten all appointments from all requests
            ->flatMap(function ($patientRequest) {
                return $patientRequest->appointments->map(function ($appointment) use ($patientRequest) {
                    $appointment->request->stage = $patientRequest->stage;
                    return $appointment;
                });
            })
            // Group by student_id + stage_id combination
            ->groupBy(function ($appointment) {
                return $appointment->student_id . '_' . $appointment->request->stage->id;
            })
            // Transform each group to the desired output format
            ->map(function ($appointmentsGroup) use ($lang) {
                $firstAppointment = $appointmentsGroup->first();
                $student = $firstAppointment->student;
                $stage = $firstAppointment->request->stage;

                $avgEvaluation = $appointmentsGroup->pluck('session')
                    ->filter()
                    ->pluck('evaluation_score')
                    ->avg();

                return [
                    'student_id' => $student->id,
                    'name' => $student->user->name[$lang] ?? null,
                    'year' => __('student_year.' . $student->year, [], $lang),
                    'stage_name' => $stage->name[$lang] ?? null,
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
        // for translation
        $lang = $request->header('Accept-Language', app()->getLocale());

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
            'session_description' => optional($firstSession)->description[$lang],
            'appointment_dates' => $appointmentDetails,
            'radiology_images' => $radiologyImages,
            'before_images' => $beforeImages,
            'after_images' => $afterImages,
        ]);
    }
}
