<?php

namespace App\Http\Controllers;

use App\Http\Requests\sessionRequest;
use App\Models\Appointment;
use App\Models\AvailableAppointment;
use App\Models\Disease;
use App\Models\EducationalContent;
use App\Models\MedicationImage;
use App\Models\Patient;
use App\Models\PatientRequest;
use App\Models\PracticalSchedule;
use App\Models\RadiologyImage;
use App\Models\Session;
use App\Models\SessionImage;
use App\Models\Stage;
use App\Models\Student;
use Barryvdh\DomPDF\Facade\Pdf;
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
        $matchingSchedule = PracticalSchedule::where('year',$student->year)
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
        $added = AvailableAppointment::create([
            'student_id' => $student->id,
            'stage_id' => $matchingSchedule->stage_id,
            'date' => $date,
            'time' => $parsedTime,
            'status' => 'on'
        ]);

        return response()->json([
            'status' => 'success',
            'available_id' => $added->id
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

        $today = Carbon::today();
        $startOfWeek = Carbon::now()->startOfWeek(Carbon::FRIDAY);

        $weekDays = [Carbon::SUNDAY, Carbon::MONDAY, Carbon::TUESDAY, Carbon::WEDNESDAY, Carbon::THURSDAY];

        $weekDates = collect($weekDays)->mapWithKeys(function ($day, $i) use ($startOfWeek) {
            $date = $startOfWeek->copy()->addDays($i + 2); // Sunday = +2 from Friday
            return [
                $date->toDateString() => [
                    'date' => $date->format('d-m-Y'),
                    'day' => $date->format('l'),
                    'times' => [],
                ]
            ];
        });

        $appointments = $student->availableAppointments()
            ->whereIn('date', $weekDates->keys())
            ->orderBy('date')
            ->orderBy('time')
            ->get();

        // Group appointments by date
        foreach ($appointments as $appointment) {
            $dateKey = Carbon::parse($appointment->date)->toDateString();

            if (Carbon::parse($dateKey)->greaterThanOrEqualTo($today)) {
                $day = $weekDates->get($dateKey);
                $day['times'][] = [
                    'id' => $appointment->id,
                    'time' => Carbon::createFromFormat('H:i:s', $appointment->time)->format('g:i A'),
                ];
                $weekDates->put($dateKey, $day);
            }
        }

        return response()->json([
            'status' => 'success',
            'appointments' => $weekDates->values()
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
                    ];})->values()
            ];})->values();

        return response()->json([
            'status' => 'success',
            'weekly_appointments' => $result
        ]);
    }

    // view the previous information for all the sessions depends on this patient request
    public function viewPreviousInfo (Request $request)
    {
        $request->validate([
            'appointment_id' => 'required|exists:appointments,id',
        ]);

        $appointment = Appointment::where('id',$request->appointment_id)->first();

        $allAppointments = Appointment::where('request_id', $appointment->request_id)
            ->with('session.images')
            ->get();

        $x_ray = RadiologyImage::where('request_id',$appointment->request_id)
            ->where('type','x-ray')->first();

        $real_image = RadiologyImage::where('request_id',$appointment->request_id)
            ->where('type','real-image')->first();

        // Collect before & after images across all sessions of the same request
        $beforeImages = [];
        $afterImages = [];
        foreach ($allAppointments as $appt) {
            if ($appt->session && $appt->session->images) {
                foreach ($appt->session->images as $img) {
                    if ($img->type === 'before-treatment')
                        $beforeImages[] = $img->image_url;
                    elseif ($img->type === 'after-treatment')
                        $afterImages[] = $img->image_url;
                }
            }
        }

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
            'previous_description' => $previousDescription,
            'x_ray' => $x_ray->image_url,
            'real_image' => $real_image->image_url,
            'before_images' => $beforeImages,
            'after_images' => $afterImages
        ]);
    }

    public function viewPatientInfo(Request $request)
    {
        $request->validate([
            'patient_id' => 'required|exists:patients,id',
        ]);
        $patient = Patient::where('id',$request->patient_id)->first();

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

    public function addDescription (Request $request)
    {
        $validated = $request->validate([
            'appointment_id' => 'required|exists:appointments,id',
            'description' => 'required|string',
        ]);

        $appointment = Appointment::find($request->appointment_id);
        $session = Session::find($request->appointment_id);

        if($session && $session->supervisor_id != null)
            return response()->json([
                'status' => 'error',
                'message' => 'Session already evaluated , you cant edit it.'
            ], 409);

        elseif($session) {
            $appointment->session->update(['description' => $request->description]);
            return response()->json([
                'status' => 'success',
            ]);
        }

        Session::create($validated);

        return response()->json([
            'status' => 'success',
        ]);
    }

    public function addTreatmentImage (Request $request)
    {
        $request->validate([
            'appointment_id' => 'required|exists:appointments,id',
            'image' => 'required|array',
            'image.*' => 'required|image|mimes:jpg,jpeg,png|max:4096',
            'type' => 'required|in:before-treatment,after-treatment'
        ]);

        $session = Session::find($request->appointment_id);

        if($session && $session->supervisor_id != null)
            return response()->json([
                'status' => 'error',
                'message' => 'Session already evaluated , you cant edit it.'
            ], 409);

        elseif(!$session)
            $session = Session::create([
                'appointment_id' => $request->appointment_id,
                'description' => 'null'
            ]);

            if ($request->hasFile('image')) {
                foreach ($request->file('image') as $image) {

                    $extension = $image->getClientOriginalExtension(); //ex: jpg
                    $filename = 'sessionId-' . $session->id . '-' . $request->type . '.' . $extension;
                    $path = $image->storeAs('sessions', $filename, 'public');

                    $session->images()->create([
                        'image_url' => $path,
                        'type' => $request->type,
                    ]);
                }
            }

        return response()->json([
            'status' => 'success',
        ]);
    }
    public function showMyPortfolio()
    {
        $user = Auth::user();
        if (!$user || !$user->student) {
            return response()->json(['message' => 'Authenticated user is not a student.'], 403);
        }

        $student = $user->student;

        $appointments = $student->appointments()
            ->with(['stage', 'session', 'patient.user'])
            ->get();

        $stagesData = $this->prepareStagesData($appointments);

        $overallGrade = $this->calculateStudentGrade($stagesData);

        return response()->json([
            'student_id' => $student->id,
            'name' => $user->name,
            'profile_image_url' => $student->profile_image_url,
            'year' => $student->year,
            'overall_grade' => $overallGrade,
            'stages' => array_values($stagesData),
        ]);
    }
    ///pdf
    public function downloadPdf()
    {
        $user = Auth::user();

        if (!$user || !$user->student) {
            return response()->json(['message' => 'Authenticated user is not a student.'], 403);
        }

        $student = $user->student;

        $appointments = $student->appointments()
            ->with(['stage', 'session', 'patient.user'])
            ->get();

        $stagesData = $this->prepareStagesData($appointments);
        $overallGrade = $this->calculateStudentGrade($stagesData);
        $data = [
            'student_id' => $student->id,
            'name' => $user->name,
            'profile_image_url' => $student->profile_image_url,
            'year' => $student->year,
            'overall_grade' => $overallGrade,
            'stages' => array_values($stagesData),
        ];
        $pdf = Pdf::loadView('pdf.portfolio', $data);

        return $pdf->download('portfolio_' . $user->name . '.pdf');
    }
    private function prepareStagesData($appointments)
    {
        $stages = [];

        foreach ($appointments as $appointment) {
            if (!$appointment->session || !$appointment->stage || !$appointment->patient) {
                continue;
            }

            $stage = $appointment->stage;
            $session = $appointment->session;
            $patient = $appointment->patient;

            $stageId = $stage->id;
            $patientId = $patient->id;


            if (!isset($stages[$stageId])) {
                $stages[$stageId] = [
                    'stage_id' => $stage->id,
                    'stage_name' => $stage->name,
                    'total_score' => 0,
                    'session_count' => 0,
                    'patients' => [],
                    'stage_average_evaluation' => null,
                ];
            }

            if ($session->evaluation_score !== null) {
                $stages[$stageId]['total_score'] += $session->evaluation_score;
                $stages[$stageId]['session_count']++;
            }

            if (!isset($stages[$stageId]['patients'][$patientId])) {
                $stages[$stageId]['patients'][$patientId] = [
                    'patient_id' => $patient->id,
                    'name' => $patient->user->name ?? 'username',
                    'session_count' => 1,
                ];
            } else {
                $stages[$stageId]['patients'][$patientId]['session_count']++;
            }
        }

        foreach ($stages as &$stage) {
            if ($stage['session_count'] > 0) {
                $stage['stage_average_evaluation'] = round($stage['total_score'] / $stage['session_count'], 2);
            }
            $stage['patients'] = array_values($stage['patients']);
            unset($stage['total_score'], $stage['session_count']);
        }

        return $stages;
    }
    //متوسط علامة الطالب تبع الستاجات
    private function calculateStudentGrade(array $stagesData): ?float
    {
        $total = 0;
        $count = 0;

        foreach ($stagesData as $stage) {
            if (isset($stage['stage_average_evaluation'])) {
                $total += $stage['stage_average_evaluation'];
                $count++;
            }
        }

        return $count > 0 ? round($total / $count, 2) : null;
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

    public function uploadProfileImage(Request $request)
    {
        $request->validate([
            'image' => 'required|image|mimes:jpg,jpeg|max:5120', // 5MB
        ]);

        $user = Auth::user();

        if (!$user || !$user->student) {
            return response()->json(['message' => 'Authenticated user is not a student.'], 403);
        }

        $student = $user->student;

        // حفظ الصورة
        $file = $request->file('image');
        $filename = 'student_' . $student->id . '.' . $file->getClientOriginalExtension();
        $file->storeAs('student_profiles', $filename, 'public');

        // نخزن فقط الاسم في قاعدة البيانات
        $student->profile_image_url = $filename;
        $student->save();

        return response()->json([
            'status' => 'success',
            'message' => 'Profile image updated successfully.',
            'profile_image_url' => asset('storage/student_profiles/' . $filename),
        ]);
    }

///////////////////////////////////////////////////المحتوى التعليمي
    public function listContents(Request $request)
    {
        $query = EducationalContent::query()->with(['images', 'supervisor','stage'])->latest();

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

        $content = EducationalContent::with(['images', 'supervisor'])->findOrFail($request->id);
        return response()->json([
            'status' => 'success',
            'content' => $content,
        ]);
    }

    public function showEducationalContentByStage(Request $request)
    {
        $typeFilter = $request->get('type');
        $stages = Stage::with([
            'educationalContents' => function ($query) use ($typeFilter) {
                $query->with(['images', 'supervisor']);
                if ($typeFilter) {
                    $query->where('type', $typeFilter);
                }
            }
        ])->get();
        $formattedStages = [];

        foreach ($stages as $stage) {
            $formattedContents = [];
            foreach ($stage->educationalContents as $content) {
                $formattedContents[] = [
                    'content_id' => $content->id,
                    'title' => $content->title,
                    'description' => $content->description,
                    'type' => $content->type,
                    'text_content' => $content->text_content,
                    'content_url' => $content->content_url,
                    'file_path' => $content->file_path,
                    'appropriate_rating' => $content->appropriate_rating,
                    'published_at' => $content->published_at,
                    'supervisor_name' => $content->supervisor->name ?? 'N/A',
                    'images' => $content->images->pluck('image_url')->toArray(),
                ];
            }
            $formattedStages[] = [
                'stage_id' => $stage->id,
                'stage_name' => $stage->name,
                'educational_contents' => $formattedContents,
            ];
        }
        return response()->json([
            'status' => 'success',
            'stages' => $formattedStages
        ]);
    }
}
