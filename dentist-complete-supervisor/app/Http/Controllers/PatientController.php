<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\AvailableAppointment;
use App\Models\MedicationImage;
use App\Models\Patient;
use App\Models\PatientRequest;
use App\Models\PracticalSchedule;
use App\Models\ReInternship;
use App\Models\Session;
use App\Models\Stage;
use App\Models\Student;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class PatientController extends Controller
{

    public function storeInformation (Request $request)
    {
        $validatedData = $request->validate([
            'height' => 'required|numeric|min:0',
            'weight' => 'required|numeric|min:0',
            'birthdate' => 'required|date|before:today|date_format:d-m-Y',
            'disease_id' => 'array',
            'disease_id.*' => 'exists:diseases,id',
            'image' => 'array',
            'image.*' => 'image|mimes:jpg,jpeg,png|max:4096',
        ]);

        // Convert birthdate to database format (Y-m-d)
        $validatedData['birthdate'] = Carbon::createFromFormat('d-m-Y', $validatedData['birthdate'])->format('Y-m-d');

        // Get or create patient
        $patient = Patient::firstOrCreate(
            ['user_id' => Auth::id()],
            $validatedData
        );

        // 🔁 If patient existed, update their data
        if (!$patient->wasRecentlyCreated) {
            $patient->update($validatedData);
        }

        ////// diseases
        if($request->has('disease_id'))
        {
        $patient->diseases()->sync($validatedData['disease_id']);
        } //attach

        ////// medicine images
        $storedImages = [];
        if($request->hasFile('image')) {
            foreach ($request->file('image') as $image) {
                $extension = $image->getClientOriginalExtension();
                $filename = 'patient-' . $patient->id . '-' . uniqid() . '.' . $extension;
                $path = $image->storeAs('medications', $filename, 'public');

                $medicationImage = $patient->medications()->create([
                    'image_url' => $path
                ]);
                $storedImages[] = [
                    'url' => asset('storage/' . $path),
                    'data' => $medicationImage
                ];
            }
        }

            return response()->json([
                'message' => 'Patient information stored successfully.',
                'patient' => $patient->load('diseases'),
                'medication_images' => $storedImages,
            ], 201);
    }

    // view all oral medicine student dentists
    public function oralMedicineDentist(Request $request)
    {
        // for translation
        $lang = $request->header('Accept-Language', app()->getLocale());

        $stageId = 3;
        $requiredCaseCount = Stage::find($stageId)->required_case_count;

        $years = PracticalSchedule::where('stage_id', $stageId)
            ->pluck('year')
            ->unique()
            ->toArray();

        $regularStudentIds = Student::whereIn('year', $years)
            ->pluck('id');

        $reinternshipStudentIds = ReInternship::where('stage_id', $stageId)
            ->where('complete', false)
            ->pluck('student_id');

        // Merge both sets of student IDs (no duplicates)
        $allStudentIds = $regularStudentIds
            ->merge($reinternshipStudentIds)
            ->unique();

        $students = Student::whereIn('id', $allStudentIds)
            ->with([
                'user:id,name',
                'appointments.request',
                'appointments.session'
            ])
            ->get();

        // Map each student to compute case count and avg evaluation
        $filteredStudents = $students->map(function ($student) use ($stageId,$lang) {
            // Count number of unique requests for this stage
            $caseCount = $student->appointments->filter(function ($appointment) use ($stageId,$lang) {
                return $appointment->request->stage_id == $stageId;
            })->pluck('request_id')->unique()->count();

            $avgEvaluation = $student->appointments
                ->pluck('session')
                ->pluck('evaluation_score')
                ->avg();

            return [
                'id' => $student->id,
                'name' => $student->user->name[$lang],
                'year' => __('student_year.' . $student->year, [], $lang),
                'profile_image' => $student->profile_image_url,
                'case_count' => $caseCount,
                'avg_evaluation' => $avgEvaluation ? round($avgEvaluation, 2) : null,
            ];
        })
            ->filter(function ($student) use ($requiredCaseCount) {
                return $student['case_count'] <= $requiredCaseCount;
            })
            ->sortBy('case_count') // Sort by ascending
            ->values();

        return response()->json([
            'status' => 'success',
            'students' => $filteredStudents
        ]);
    }


    public function requestStatus(Request $request)
    {
        // for translation
        $lang = $request->header('Accept-Language', app()->getLocale());

        $patient = Patient::where('user_id', Auth::id())->firstOrFail();

        // Get the latest patient request
        $latestRequest = $patient->patientRequests()->latest()->first();

        // Case 1: No request yet
        if ($latestRequest->complete == 1) {
            return response()->json([
                'id' => 1,
                'status' => 'Please visit the radiology department first.',
            ]);
        }

        // Case 2: Request is under processing
        if ($latestRequest->complete == 0 && $latestRequest->status === 'under processing') {
            return response()->json([
                'id' => 2,
                'status' => 'Your request is currently under processing.',
            ]);
        }

        // Case 3: Request is processed — show eligible students
        if ($latestRequest->complete == 0 && $latestRequest->status === 'processed') {
            $stageId = $latestRequest->stage_id;

            // Get required case count for this stage
            $stage = Stage::find($stageId);
            $requiredCaseCount = $stage->required_case_count;

            // Check if this request already has an appointment (اذا كان عم يسجل اول مرة فحيطلع ليستت الطلاب و اما المرات الجاية خلص الطالب صار ثابت)
            $existingAppointment = $latestRequest->appointments->first();
            if ($existingAppointment) {
                return response()->json([
                    'status' => 'This is not the first appointment , its the same student.',
                    'stage_id' => $stageId,
                    'student_id' => $existingAppointment->student_id,
                ]);
            }

            $years = PracticalSchedule::where('stage_id', $stageId)
                ->pluck('year')
                ->unique()
                ->toArray();

            $regularStudentIds = Student::whereIn('year', $years)
                ->pluck('id');

            $reinternshipStudentIds = ReInternship::where('stage_id', $stageId)
                ->where('complete', false)
                ->pluck('student_id');

            // Merge both sets of student IDs (no duplicates)
            $allStudentIds = $regularStudentIds
                ->merge($reinternshipStudentIds)
                ->unique();

            $students = Student::whereIn('id', $allStudentIds)
                ->with([
                    'user:id,name',
                    'appointments.request',
                    'appointments.session'
                ])
                ->get();

            // Map each student with case count and average evaluation
            $filteredStudents = $students->map(function ($student) use ($stageId,$lang) {
                // Count requests in this stage (through appointments)
                $caseCount = $student->appointments->filter(function ($appointment) use ($stageId,$lang) {
                    return $appointment->request->stage_id == $stageId;
                })->unique('request.id')->count();

                $avgEvaluation = $student->appointments
                    ->pluck('session')
                    ->pluck('evaluation_score')
                    ->avg();

                return [
                    'id' => $student->id,
                    'name' => $student->user->name[$lang],
                    'year' => __('student_year.' . $student->year, [], $lang),
                    'profile_image' => $student->profile_image_url,
                    'case_count' => $caseCount,
                    'avg_evaluation' => $avgEvaluation ? round($avgEvaluation, 2) : null,
                ];
            })
                ->filter(function ($student) use ($requiredCaseCount) {
                    return $student['case_count'] <= $requiredCaseCount;
                })
                ->sortBy('case_count') // Sort by ascending number
                ->values();

            return response()->json([
                'id' => 3,
                'status' => 'success',
                'stage_id' => $stageId,
                'stage_name' => $stage->name[$lang],
                'students' => $filteredStudents,
            ]);
        }
    }


    public function viewAvailableAppointments(Request $request)
    {
        // for translation
        $lang = $request->header('Accept-Language', app()->getLocale());

        $request->validate([
            'student_id' => 'required|exists:students,id',
            'stage_id' => 'required|exists:stages,id',
        ]);

        // Get available appointments from database
        $availableAppointments = AvailableAppointment::where('student_id', $request->student_id)
            ->where('stage_id', $request->stage_id)
            ->where('status', 'on')
            ->whereDate('date', '>=', now())
            ->orderBy('date')
            ->orderBy('time')
            ->get();

        // Group by date
        $groupedByDate = $availableAppointments->groupBy('date');

        // Create a collection for all week days (Sunday to Thursday)
        $weekDays = collect(['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday']);

        // Get dates for the next occurrence of each day
        $nextWeekDays = $weekDays->mapWithKeys(function ($day) {
            $date = Carbon::now()->next($day);
            return [$day => $date->format('Y-m-d')];
        });

        // Build the response structure for all days
        $response = $weekDays->map(function ($day) use ($groupedByDate, $nextWeekDays, $lang) {
            $date = $nextWeekDays[$day];
            $appointments = $groupedByDate->get($date, collect());

            return [
                'date' => $appointments->isNotEmpty() ? $date : null,
                'day' => __('days.' . $day, [], $lang),
                'status' => $appointments->isNotEmpty() ? 'on' : 'off',
                'times' => $appointments->isEmpty()
                    ? []
                    : $appointments->map(function ($item) {
                        return [
                            'id' => $item->id,
                            'time' => $item->time,
                        ];
                    })->values()->toArray()
            ];
        });

        return response()->json([
            'status' => 'success',
            'available_appointments' => $response
        ]);
    }


    public function bookAvailableAppointment(Request $request)
    {
        $request->validate([
            'available_id' => 'required|exists:available_appointments,id',
        ]);

        $patient = Patient::where('user_id', Auth::id())->firstOrFail();

        // Get the available slot
        $available = AvailableAppointment::where('id',$request->available_id)->first();

        // Double check it's still available
        if ($available->status !== 'on') {
            return response()->json([
                'status' => 'error',
                'message' => 'This appointment is no longer available.',
            ], 409);
        }

        // Create the official appointment
        $appointment = Appointment::create([
            'student_id' => $available->student_id,
            'date'       => $available->date,
            'time'       => $available->time,
            'request_id' => $patient->patientRequests()
                ->where('stage_id', $available->stage_id)
                ->where('status', 'processed')
                ->latest()->value('id'), // link to request if exists
        ]);

        // Delete the available slot
        $available->delete();

        return response()->json([
            'status' => 'success'
        ]);
    }

}
