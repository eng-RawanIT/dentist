<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\EducationalContent;
use App\Models\EducationalImage;
use App\Models\PracticalSchedule;
use App\Models\Session;
use App\Models\Stage;
use App\Models\Student;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;


class SupervisorController extends Controller
{
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
            'appropriate_rating' => 'required|integer|min:1|max:5',
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
        $contents = EducationalContent::where('supervisor_id', Auth::id())->with('images', 'stage')->get();
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
            $daySchedules = $schedules->where('days', $day)->values();

            return [
                'day' => $day,
                'schedules' => $daySchedules->map(function ($item) {
                    return [
                        'year' => $item->year,
                        'stage' => Stage::find($item->stage_id)->name,
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
            'appointment.patient.user',
            'appointment.request.stage',
            'appointment.student.user',
        ])->findOrFail($sessionId);

        return response()->json([
            'student_name' => $session->appointment->student->user->name,
            'student_image' => $session->appointment->student->profile_image_url,
            'stage' => $session->appointment->request->stage->name ?? 'N/A',
            'patient_name' => $session->appointment->patient->user->name ?? 'N/A',
            'description' => $session->description,
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
        $patientName = optional($appointment->patient->user)->name;
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
            'stage_name' => $stage->name,
            'student_name' => $student->user->name,
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
            'appointment.patient.user',
            'appointment.request.stage',
            'appointment.student.user',
        ])->findOrFail($sessionId);

        return response()->json([
            'student_name' => $session->appointment->student->user->name,
            'student_image' => $session->appointment->student->profile_image_url,
            'stage' => $session->appointment->request->stage->name ?? 'N/A',
            'patient_name' => $session->appointment->patient->user->name ?? 'N/A',
            'description' => $session->description,
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

        return response()->json(['message' => 'Session evaluated and archived status updated successfully.']);
    }


}
