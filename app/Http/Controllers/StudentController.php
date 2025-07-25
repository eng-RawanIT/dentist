<?php

namespace App\Http\Controllers;

use App\Http\Requests\sessionRequest;
use App\Models\Appointment;
use App\Models\AvailableAppointment;
use App\Models\Disease;
use App\Models\EducationalContent;
use App\Models\MedicationImage;
use App\Models\Patient;
use App\Models\PracticalSchedule;
use App\Models\RadiologyImage;
use App\Models\Session;
use App\Models\SessionImage;
use App\Models\Student;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class StudentController extends Controller
{

    public function addAvailableAppointment(Request $request)
    {
        $request->validate([
            'date' => 'required|date|after_or_equal:today|date_format:d-m-Y',
            'time' => 'required|string',
        ]);

        $date = Carbon::createFromFormat('d-m-Y', $request->date)->format('Y-m-d');
        $dayOfWeek = Carbon::parse($request->date)->format('l'); // e.g., 'Monday'

        try {// Parse time string like "11 AM" or "2:30 PM" to H:i:s
            $parsedTime = Carbon::createFromFormat('g A', $request->time)->format('H:i:s');
        } catch (\Exception $e) {
            try {// Try with minutes (e.g. "2:30 PM")
                $parsedTime = Carbon::createFromFormat('g:i A', $request->time)->format('H:i:s');
            } catch (\Exception $e) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Invalid time format. Please use formats like "11 AM" or "2:30 PM".'
                ], 422);
            }
        }

        $student = Student::where('user_id', Auth::id())->firstOrFail();

        // Find matching practical schedule based on date/time only
        $matchingSchedule = PracticalSchedule::where('year', $student->year)
            ->where('days', $dayOfWeek)
            ->where('start_time', '<=', $parsedTime)
            ->where('end_time', '>=', $parsedTime)
            ->first();

        if (!$matchingSchedule) {
            return response()->json([
                'status' => 'error',
                'message' => 'You do not have a scheduled stage at this time.'
            ], 400);
        }

        // Check for conflicting availability
        $conflict = AvailableAppointment::where('student_id', $student->id)
            ->where('date', $date)
            ->where('time', $parsedTime)
            ->exists();

        if ($conflict) {
            return response()->json([
                'status' => 'conflict',
                'message' => 'You already have an availability at this time.'
            ], 409);
        }

        // Create the availability using the stage_id from the schedule
        AvailableAppointment::create([
            'student_id' => $student->id,
            'stage_id' => $matchingSchedule->stage_id,
            'date' => $date,
            'time' => $parsedTime,
            'status' => 'on'
        ]);

        return response()->json([
            'status' => 'success',
        ]);
    }

    public function deleteAvailableAppointment(Request $request)
    {
        $request->validate([
            'available_id' => 'required|exists:available_appointments,id',
        ]);

        $student = Student::where('user_id', Auth::id())->firstOrFail();

        // Find the appointment and ensure it belongs to this student
        $appointment = AvailableAppointment::where('id', $request->available_id)
            ->where('student_id', $student->id)
            ->first();

        if (!$appointment) {
            return response()->json([
                'status' => 'error',
                'message' => 'Appointment not found or does not belong to you.'
            ], 404);
        }

        $appointment->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Available appointment deleted successfully.'
        ]);
    }


    public function changeDayStatus(Request $request)
    {
        $request->validate([
            'date' => 'required|date|after_or_equal:today|date_format:d-m-Y',
            'status' => 'required|string|in:on,off'
        ]);

        $date = Carbon::createFromFormat('d-m-Y', $request->date)->format('Y-m-d');

        $student = Student::where('user_id', Auth::id())->firstOrFail();

        if ($request->status === 'off') {
            // Delete all available appointments for this date
            AvailableAppointment::where('student_id', $student->id)
                ->where('date', $date)
                ->delete();
            return response()->json([
                'status' => 'deleted success',
            ]);
        }


        AvailableAppointment::where('student_id', $student->id)
            ->where('date', $date)
            ->update(['status' => $request->status]);

        return response()->json([
            'status' => 'updated success',
        ]);
    }

    public function viewMyAppointment()
    {
        $student = Student::where('user_id', Auth::id())->firstOrFail();

        $appointments = $student->availableAppointments()
            //->where('status', 'on')
            ->whereDate('date', '>=', now())
            ->with('stage') // eager load stage info
            ->orderBy('stage_id')
            ->orderBy('date')
            ->orderBy('time')
            ->get();

        // Group by stage, then by date
        $grouped = $appointments
            ->groupBy('stage_id')
            ->map(function ($stageAppointments) {
                $stageName = optional($stageAppointments->first()->stage)->name ?? 'Unknown Stage';

                // Group by date inside this stage
                $byDate = $stageAppointments->groupBy('date')->map(function ($dateGroup, $date) {
                    return [
                        'date' => $date,
                        'day' => Carbon::parse($date)->format('l'),
                        'times' => $dateGroup->map(function ($item) {
                            return [
                                'id' => $item->id,
                                'time' => Carbon::createFromFormat('H:i:s', $item->time)->format('g:i A'),
                            ];
                        })->values(),
                    ];
                })->values();

                return [
                    'stage_name' => $stageName,
                    'days' => $byDate,
                ];
            })->values();

        return response()->json([
            'status' => 'success',
            'available_appointments' => $grouped
        ]);
    }

    public function weeklySchedule()
    {
        $student = Student::where('user_id', Auth::id())->firstOrFail();

        $appointments = Appointment::with(['patient.user', 'stage'])
            ->where('student_id', $student->id)
            ->whereDate('date', '>=', now()->startOfDay())
            ->whereDoesntHave('session') // Not treated yet
            ->orderBy('date')
            ->orderBy('time')
            ->get();

        $result = $appointments->groupBy('date')->map(function ($dailyAppointments, $date) {
            return [
                'date' => $date,
                'day' => Carbon::parse($date)->format('l'),
                'appointments' => $dailyAppointments->map(function ($appointment) {
                    return [
                        'appointment_id' => $appointment->id,
                        'patient_name' => $appointment->patient->user->name,
                        'time' => Carbon::createFromFormat('H:i:s', $appointment->time)->format('g:i A'),
                        'stage_name' => $appointment->stage->name,
                    ];
                })->values()
            ];
        })->values();

        return response()->json([
            'status' => 'success',
            'weekly_appointments' => $result
        ]);
    }

    public function viewRadiologyImages(Request $request)
    {
        $request->validate([
            'appointment_id' => 'required|exists:appointments,id',
        ]);

        $appointment = Appointment::where('id', $request->appointment_id)->first();

        $x_ray = RadiologyImage::where('request_id', $appointment->request_id)
            ->where('type', 'x-ray')->first();

        $real_image = RadiologyImage::where('request_id', $appointment->request_id)
            ->where('type', 'real-image')->first();

        // Get previous appointment (before the current one) under same request
        $previousAppointment = Appointment::where('request_id', $appointment->request_id)
            ->where('date', '<', $appointment->date)
            ->where('id', '!=', $appointment->id)
            ->orderByDesc('date')
            ->first();

        // Get the session description if available
        $previousDescription = $previousAppointment && $previousAppointment->session
            ? $previousAppointment->session->description
            : null;

        return response()->json([
            'status' => 'success',
            'x_ray' => $x_ray->image_url,
            '$real_image' => $real_image->image_url,
            'previous_description' => $previousDescription,
        ]);
    }

    public function viewPatientInfo(Request $request)
    {
        $request->validate([
            'patient_id' => 'required|exists:patients,id',
        ]);
        $patient = Patient::where('id', $request->patient_id)->first();

        return response()->json([
            'status' => 'success',
            'name' => $patient->user->name,
            'birthdate' => $patient->birthdate,
            'height' => $patient->height,
            'weight' => $patient->weight,
            'diseases' => $patient->diseases->pluck('name'),
            'medication' => $patient->medications->pluck('image_url')
        ]);
    }

    public function sessionInformation(sessionRequest $request)
    {
        $validated = $request->validated();

        // Check if session already exists for this appointment
        if (Session::where('appointment_id', $validated['appointment_id'])->exists()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Session already exists for this appointment.'
            ], 409);
        }

        $session = Session::create($validated);


        if (!empty($validated['images'])) {
            foreach ($validated['images'] as $imageData) {
                $type = $imageData['type'];
                $image = $imageData['file'];

                $extension = $image->getClientOriginalExtension(); //ex: jpg
                $filename = 'session-' . $session->id . '-' . $type . '.' . $extension;
                $path = $image->storeAs('sessionImages', $filename, 'public');

                $session->images()->create([
                    'image_url' => $path,
                    'type' => $type,
                ]);
            }
        }

        return response()->json([
            'status' => 'success',
            'session' => $session->only(['id', 'appointment_id', 'description']),
        ]);
    }

    public function addAppointmentToPatient(Request $request)
    {
        $request->validate([
            'appointment_id' => 'required|exists:appointments,id',
            'date' => 'required|date|after_or_equal:today|date_format:d-m-Y',
            'time' => 'required|string'
        ]);

        $date = Carbon::createFromFormat('d-m-Y', $request->date)->format('Y-m-d');
        $dayOfWeek = Carbon::parse($request->date)->format('l'); // e.g., 'Monday'

        try {// Parse time string like "11 AM" or "2:30 PM" to H:i:s
            $parsedTime = Carbon::createFromFormat('g A', $request->time)->format('H:i:s');
        } catch (\Exception $e) {
            try {// Try with minutes (e.g. "2:30 PM")
                $parsedTime = Carbon::createFromFormat('g:i A', $request->time)->format('H:i:s');
            } catch (\Exception $e) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Invalid time format. Please use formats like "11 AM" or "2:30 PM".'
                ], 422);
            }
        }

        $student = Student::where('user_id', Auth::id())->firstOrFail();
        $parentAppointment = Appointment::find($request->appointment_id);

        // Check that student's schedule matches stage, time, and day
        $matchingSchedule = PracticalSchedule::where('year', $student->year)
            ->where('stage_id', $parentAppointment->stage_id)
            ->where('days', $dayOfWeek)
            ->where('start_time', '<=', $parsedTime)
            ->where('end_time', '>=', $parsedTime)
            ->first();

        if (!$matchingSchedule) {
            return response()->json([
                'status' => 'error',
                'message' => 'You do not have a scheduled stage at this time.'
            ], 400);
        }

        //check the appointment not existing befor
        $alreadyExists = Appointment::where('student_id', $student->id)
            ->where('date', $date)
            ->where('time', $parsedTime)
            ->exists();

        if ($alreadyExists) {
            return response()->json([
                'status' => 'error',
                'message' => 'You already have an appointment for the same date and time.'
            ], 409);
        }


        Appointment::create([
            'request_id' => $parentAppointment->request_id,
            'patient_id' => $parentAppointment->patient_id,
            'student_id' => $student->id,
            'stage_id' => $parentAppointment->stage_id,
            'date' => $date,
            'time' => $parsedTime,
        ]);

        return response()->json([
            'status' => 'success',
        ]);

    }

    public function showMyPortfolio()
    {
        $user = Auth::user();

        if (!$user || !$user->student) {
            return response()->json(['message' => 'Authenticated user is not a student'], 403);
        }

        $student = $user->student;

        $appointments = $student->appointments()
            ->with(['session.images', 'patient.user'])
            ->get();

        $sessions = [];
        $totalScore = 0;
        $sessionCount = 0;

        foreach ($appointments as $appointment) {
            if ($appointment->session) {
                $session = $appointment->session;

                if ($session->evaluation_score !== null) {
                    $totalScore += $session->evaluation_score;
                    $sessionCount++;
                }

                $sessions[] = [
                    'session_id' => $session->id,
                    'session_date' => $appointment->date,
                    'evaluation_score' => $session->evaluation_score,
                    'description' => $session->description,
                    'images' => $session->images->pluck('image_url'),
                    'patient' => [
                        'id' => $appointment->patient->id ?? null,
                        'name' => $appointment->patient->user->name ?? 'N/A',
                        'gender' => $appointment->patient->gender ?? null,
                        'birthdate' => $appointment->patient->birthdate ?? null,
                    ],
                ];
            }
        }

        $averageGrade = $sessionCount > 0 ? round($totalScore / $sessionCount, 2) : null;

        $portfolio = [
            'student_id' => $student->id,
            'name' => $user->name,
            'image' => $student->profile_image_url,
            'year' => $student->year,
            'grade' => $averageGrade,
            'sessions' => $sessions,
        ];

        return response()->json($portfolio);
    }

    public function getStudentStagesWithSessions()
    {
        $user = Auth::user();

        if (!$user || !$user->student) {
            return response()->json(['message' => 'Authenticated user is not a student'], 403);
        }

        $student = $user->student;

        // Get all appointments of this student that have sessions
        $appointmentsWithSessions = $student->appointments()
            ->whereHas('session')
            ->with(['session', 'stage'])
            ->get();

        // Group sessions by stage
        $groupedByStage = $appointmentsWithSessions->groupBy(function ($appointment) {
            return $appointment->stage->id;
        })->map(function ($appointments, $stageId) {
            $stage = $appointments->first()->stage;

            return [
                'stage_id' => $stage->id,
                'stage_name' => $stage->name,
                'sessions' => $appointments->map(function ($appointment) {
                    return [
                        'session_id' => $appointment->session->id,
                        'date' => $appointment->date,
                        'time' => $appointment->time,
                        'evaluation_score' => $appointment->session->evaluation_score,
                        'description' => $appointment->session->description,
                    ];
                })->values()
            ];
        })->values();

        return response()->json([
            'student' => [
                'id' => $student->id,
                'name' => $user->name,
                'year' => $student->year,
            ],
            'stages' => $groupedByStage
        ]);
    }
    public function getStudentQrCodeData()
    {
        $user = Auth::user();
        $student = $user->student;

        if (!$student) {
            return response()->json(['message' => 'User is not a student'], 403);
        }

        $lastSession = $student->appointments()
            ->whereHas('session')
            ->with('session')
            ->orderByDesc('date')
            ->orderByDesc('time')
            ->first();

        if (!$lastSession || !$lastSession->session) {
            return response()->json(['message' => 'No session found'], 404);
        }

        $sessionId = $lastSession->session->id;
        ///////////////////////////////////////////////هاد شي مبدأي لحتى نعمل واجهات المشرف
        $url = "https://yourdomain.com/sessions/{$sessionId}";

        return response()->json([
            'qr_string' => $url,
            'session_id' => $sessionId,
        ]);
    }
///////////////////////////////////////////////////المحتوى التعليمي
    public function listContents(Request $request)
    {
        $query = EducationalContent::query()->with('images')->latest();

        // Filter by type if provided
        if ($request->has('type')) {
            $query->where('type', $request->type);
        }

        $contents = $query->get();

        return response()->json(['status' => 'success', 'contents' => $contents]);
    }

    public function showContent(Request $request)
    {
        $request->validate([
            'id' => 'required|exists:educational_contents,id',
        ]);

        $content = EducationalContent::with('images', 'supervisor')->findOrFail($request->id);

        return response()->json([
            'status' => 'success',
            'content' => $content,
        ]);
    }
}
