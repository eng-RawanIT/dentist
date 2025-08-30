<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\EducationalContent;
use App\Models\EducationalImage;
use App\Models\PracticalSchedule;
use App\Models\ReInternship;
use App\Models\Session;
use App\Models\Stage;
use App\Models\Student;
use App\Models\StudentAbsence;
use App\Models\User;
use App\Services\NotificationService;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;


class SupervisorController extends Controller
{
    public function __construct(private NotificationService $notificationService) {}

    public function storeEducationalContent(Request $request)
    {

        $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'type' => 'required|in:article,pdf,link,image',
            'text_content' => 'nullable|string',
            'content_url' => 'nullable|url',
            'file' => 'nullable|file|max:10240',
            'images.*' => 'nullable|image|max:5120',
            'stage_id' => 'required|integer|exists:stages,id',
            'appropriate_rating' => 'required|numeric|min:1|max:5',
        ]);

        $user = Auth::user();

        if (!$user || $user->supervisor) {
            return response()->json(['message' => 'Unauthorized. Only supervisors can add educational content related to sessions.'], 403);
        }


        $stage = Stage::find($request->stage_id);
        if (!$stage) {
            return response()->json(['message' => 'Stage not found.'], 404);
        }


        $contentData = [
            'supervisor_id' => Auth::id(),
            'stage_id' => $request->stage_id,
            'title' => $request->title,
            'description' => $request->description,
            'type' => $request->type,
            'text_content' => $request->text_content,
            'content_url' => $request->content_url,
            'appropriate_rating' => $request->appropriate_rating,
            'published_at' => now(),
        ];

        if ($request->hasFile('file')) {
            if (!in_array($request->type, ['pdf', 'image'])) {
                return response()->json(['message' => 'File upload is only allowed for PDF or Image content types.'], 400);
            }
            $path = $request->file('file')->store('educational_files', 'public');
            $contentData['file_path'] = $path;
        }
        $content = EducationalContent::create($contentData);


        if ($request->type === 'article' && $request->hasFile('images')) {
            foreach ($request->file('images') as $image) {
                $imgPath = $image->store('educational_images', 'public');
                EducationalImage::create([
                    'educational_content_id' => $content->id,
                    'image_url' => $imgPath,
                ]);
            }
        } elseif ($request->hasFile('images') && $request->type !== 'article') {
            return response()->json(['message' => 'Multiple images are only allowed for "article" type content. Use "file" for single image/pdf upload.'], 400);
        }
        $content->load('images');
        $content->load('stage');

        return response()->json([
            'status' => 'success',
            'message' => 'Educational content created successfully.',
            'content' => $content
        ], 201);

    }

    public function myEducationalContents()
    {
        $contents = EducationalContent::where('supervisor_id', Auth::id())->with('images','stage')->get();
        return response()->json(['status' => 'success', 'contents' => $contents]);
    }

    public function deleteContent($id)
    {
        $educationalContent = EducationalContent::find($id);
        if (!$educationalContent) {
            return response()->json(['status' => 'error', 'message' => 'Educational content with this ID does not exist.'], 404);
        }
        if ($educationalContent->supervisor_id !== Auth::id()) {
            return response()->json(['status' => 'error', 'message' => 'You are not authorized to delete this content. It belongs to another supervisor.'], 403);
        }
        if ($educationalContent->file_path) {
            Storage::delete($educationalContent->file_path);
        }
        foreach ($educationalContent->images as $image) {
            Storage::delete($image->image_url);
            $image->delete();
        }
        $educationalContent->delete();

        return response()->json(['status' => 'success', 'message' => 'Educational content deleted successfully.'], 200);
    }

    //weekly schedule
    public function weeklySchedule()
    {
        $supervisorId = Auth::id();

        $schedules = PracticalSchedule::where('supervisor_id', $supervisorId)->get();

        $weekDays = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday'];

        // Group schedules by day
        $weeklySchedule = collect($weekDays)->map(function ($day) use ($schedules) {
            $daySchedules = $schedules->where('days',$day)->values();

            return [
                'day' => $day,
                'schedules' => $daySchedules->map(function ($item) {
                    return [
                        'practical_id' => $item->id,
                        'year' => $item->year,
                        'stage_id' => $item->stage_id,
                        'stage' => Stage::find($item->stage_id)->name['en'],
                        'from' => Carbon::createFromFormat('H:i:s', $item->start_time)->format('g:i A'),
                        'to' => Carbon::createFromFormat('H:i:s', $item->end_time)->format('g:i A'),
                    ];
                })
            ];
        });

        return response()->json([
            'status' => 'success',
            'weekly_schedule' => $weeklySchedule
        ]);
    }

    ////////////QR-code
    public function handleScannedQRCode(Request $request)
    {
        $user = Auth::user();
        if (!$user ) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }
        $roleName = $user->role->name;
        if ($roleName === 'supervisor') {
            return $this->handleSupervisorView($request->session_id);
        }
        if ($roleName === 'doctor') {
            return $this->handleDoctorView($request->student_id, $request->session_id);
        }
        return response()->json(['message' => 'Unauthorized. Only supervisors or doctors can access this resource.'], 403);
    }

    private function handleSupervisorView($sessionId)
    {
        $session = Session::with([
            'images',
            'appointment.request.patient.user', //here
            'appointment.request.stage',
            'appointment.student.user',
        ])->findOrFail($sessionId);

        return response()->json([
            'student_name' => $session->appointment->student->user->name['en'],
            'student_image' => $session->appointment->student->profile_image_url,
            'stage' => $session->appointment->request->stage->name['en'] ?? 'N/A',
            'patient_name' => $session->appointment->request->patient->user->name ?? 'N/A', //here
            'description' => $session->description['en'],
            'radiology_image' => $session->appointment->request->radiologyImages()->first()?->image_url,
            'before_images' => $session->images->where('type', 'before-treatment')->pluck('image_url'),
            'after_images' => $session->images->where('type', 'after-treatment')->pluck('image_url'),
        ]);
    }

    private function handleDoctorView($studentId, $sessionId)
    {
        $session = Session::with('appointment.request.stage')->findOrFail($sessionId);

        $appointment = $session->appointment;
        $request = $appointment?->request;
        $stage = $request?->stage;

        if (!$appointment || !$request || !$stage) {
            return response()->json(['message' => 'Stage not found for the session'], 404);
        }
        $stageId = $stage->id;
        $patientName = optional($appointment->request->patient->user)->name; //here
        $supervisorComments = $session->supervisor_comments;
        $evaluationScore = $session->evaluation_score;
        $student = Student::with('user')->where('user_id', $studentId)->first();
        if (!$student || !$student->user) {
            return response()->json(['message' => 'Student not found'], 404);
        }
        $lastAppointmentDate = Appointment::where('student_id', $studentId)
            ->whereHas('request', function ($q) use ($stageId) {
                $q->where('stage_id', $stageId);
            })
            ->latest('date')
            ->value('date');

        return response()->json([
            'student_id' => (int) $studentId,
            'stage_id' => $stageId,
            'stage_name' => $stage->name['en'],
            'student_name' => $student->user->name['en'],
            'student_image' => $student->profile_image_url,
            'evaluation_score' => $evaluationScore,
            'supervisor_comments' => $supervisorComments,
            'patient_name' => $patientName,
            'appointment_date' => $lastAppointmentDate,
        ]);
    }

    public function doctorViewCase($sessionId)
    {
        $user = Auth::user();
        $session = Session::with([
            'images',
            'appointment.request.patient.user', //here
            'appointment.request.stage',
            'appointment.student.user',
        ])->findOrFail($sessionId);

        return response()->json([
            'student_name' => $session->appointment->student->user->name['en'],
            'student_image' => $session->appointment->student->profile_image_url,
            'stage' => $session->appointment->request->stage->name['en'] ?? 'N/A',
            'patient_name' => $session->appointment->request->patient->user->name ?? 'N/A', //here
            'description' => $session->description['en'],
            'radiology_image' => $session->appointment->request->radiologyImages()->first()?->image_url,
            'before_images' => $session->images->where('type', 'before-treatment')->pluck('image_url'),
            'after_images' => $session->images->where('type', 'after-treatment')->pluck('image_url'),
        ]);
    }
///evaluate
    public function evaluateSession(Request $request)
    {
        $request->validate([
            'session_id' => 'required|exists:sessions,id',
            'evaluation_score' => 'required|numeric|min:0',
            'supervisor_comments' => 'nullable|string',
            'is_archived' => 'required|boolean',
        ]);

        $user = Auth::user();

        $session = Session::findOrFail($request->session_id);

        $session->update([
            'evaluation_score' => $request->evaluation_score,
            'supervisor_comments' => $request->supervisor_comments,
            'is_archived' => $request->is_archived,
            'supervisor_id' => $user->id,
        ]);

        $appointment = Appointment::find($session->appointment_id);
        $student = Student::find($appointment->student_id);

        $this->notificationService->notifyUser(
            $student->user->id ,
            "evaluated succussfully",
            "your supervisor : $user->name evaluate your internship",
        );

        return response()->json(['message' => 'Session evaluated and archived status updated successfully.']);
    }
//////الحضور والغياب
    public function getStudentsForPracticalSchedule(Request $request)
    {
        $schedule = PracticalSchedule::with(['students.user', 'stage'])->find($request->practicalSchedule_Id);

        if (!$schedule) {
            return response()->json(['message' => 'Schedule not found'], 404);
        }

        $studentsData = $schedule->students->map(function ($student) use ($schedule) {
            return [
                'student_id' => $student->id,
                'name' => $student->user->name['en'],
                'profile_image_url' => $student->profile_image_url,
                'year' => $student->year,
                'group_number' => $student->pivot->group_number,
//                'stage_name' => $schedule->stage->name,
            ];
        });

        return response()->json([
            'status' => 'success',
            'schedule_id' => $schedule->id,
            'day' => $schedule->days,
            'location' => $schedule->location,
            'supervisor_id' => $schedule->supervisor_id,
            'stage_id' => $schedule->stage_id,
            'stage_name' => $schedule->stage->name['en'],
            'students' => $studentsData,
        ]);
    }

    public function recordAbsences(Request $request)
    {
        $request->validate([
            'practicalSchedule_Id' => 'required|exists:practical_schedules,id',
            'absent_students' => 'required|array',
            'absent_students.*' => 'integer|exists:students,id',
        ]);

        $scheduleId = $request->practicalSchedule_Id;
        $studentIds = $request->absent_students;
        $today = Carbon::today();

        foreach ($studentIds as $studentId) {
            $alreadyExists = StudentAbsence::where('practical_schedule_id', $scheduleId)
                ->where('student_id', $studentId)
                ->whereDate('date', $today)
                ->exists();

            if (!$alreadyExists) {
                StudentAbsence::create([
                    'practical_schedule_id' => $scheduleId,
                    'student_id' => $studentId,
                    'date' => $today,
                ]);
            }
        }

        return response()->json([
            'message' => 'Absences recorded successfully.',
            'recorded_count' => count($studentIds),
        ]);
    }
    //pdf للحضور والغياب

    public function downloadAttendanceReport(Request $request)
    {
        $request->validate([
            'practical_schedule_id' => 'required|exists:practical_schedules,id',
            'date' => 'required|date',
        ]);

        $schedule = PracticalSchedule::with(['stage', 'students.user', 'supervisor'])
            ->findOrFail($request->practical_schedule_id);

        $absentIds = StudentAbsence::where('practical_schedule_id', $schedule->id)
            ->where('date', $request->date)
            ->pluck('student_id')
            ->toArray();

        $getName = function ($name) {
            return is_array($name) ? ($name['en'] ?? reset($name)) : $name;
        };

        $studentsData = $schedule->students->map(function ($student) use ($absentIds, $getName) {
            return [
                'name' => $getName($student->user->name['en']),
                'status' => in_array($student->id, $absentIds) ? 'Absent' : 'Present',
            ];
        });

        $pdf = Pdf::loadView('pdf.attendance_report', [
            'supervisor_name' => $getName($schedule->supervisor->name),
            'stage_name' => $schedule->stage->name['en'],
            'schedule_name' => $schedule->day . ' - ' . $schedule->location,
            'date' => $request->date,
            'students' => $studentsData,
        ]);

        $filename = "Attendance_Report_{$schedule->id}_" . date('Ymd', strtotime($request->date)) . '.pdf';
        Storage::disk('public')->put("attendance_reports/{$filename}", $pdf->output());

        return response()->json([
            'message' => 'PDF report generated successfully.',
            'download_url' => asset("storage/attendance_reports/{$filename}")
        ]);
    }

    public function studentsMarks(Request $request)
    {
        $user = Auth::user();

        $schedule = PracticalSchedule::with(['students.user', 'stage', 'supervisor'])
            ->where('supervisor_id', $user->id)
            ->where('year', 'LIKE', '%' . $request->year . '%')
            ->firstOrFail();

        $reInternshipStudentIds = ReInternship::pluck('student_id')->toArray();

        $studentsData = $schedule->students->map(function ($student) use ($reInternshipStudentIds, $schedule) {
            $totalScore = Appointment::where('student_id', $student->id)
                ->whereHas('session')
                ->with('session')
                ->get()
                ->sum(fn($appointment) => $appointment->session->evaluation_score ?? 0);
            $absenceCount = DB::table('student_absences')
                ->where('practical_schedule_id', $schedule->id)
                ->where('student_id', $student->id)
                ->count();

            return [
                'name' => $student->user->name['en'] ?? 'N/A',
                'academic_year' => $student->year ?? 'N/A',
                'group_number' => $student->pivot->group_number ?? null,
                'score' => $totalScore,
                'absence_count' => $absenceCount,
                'is_reinternship' => in_array($student->id, $reInternshipStudentIds),
            ];
        });

        $normalStudents = $studentsData->where('is_reinternship', false)->sortBy('group_number');
        $reinternshipStudents = $studentsData->where('is_reinternship', true);
        $finalList = $normalStudents->merge($reinternshipStudents);

        $pdf = Pdf::loadView('pdf.yearly_students_report', [
            'supervisor_name' => $schedule->supervisor->name ?? 'N/A',
            'stage_name' => $schedule->stage->name['en'] ?? 'N/A',
            'schedule_name' => $schedule->days . ' - ' . $schedule->location,
            'students' => $finalList,
            'year' => $request->year,
        ]);

        $filename = "reports/yearly_students_report_{$schedule->id}_" . date('Ymd') . ".pdf";
        Storage::disk('public')->put($filename, $pdf->output());

        return response()->json([
            'message' => 'PDF report generated successfully.',
            'download_url' => asset("storage/{$filename}")
        ]);
    }

}
