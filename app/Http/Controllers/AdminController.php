<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreUserRequest;
use App\Models\Patient;
use App\Models\PatientRequest;
use App\Models\PracticalSchedule;
use App\Models\ReInternship;
use App\Models\Stage;
use App\Models\Student;
use App\Models\User;
use App\Services\OtpService;
use App\Services\StudentDistributionService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Stichoza\GoogleTranslate\GoogleTranslate;
use function Illuminate\Events\queueable;

class AdminController extends Controller
{

    public function AddUser(StoreUserRequest $request)
    {
        $validated = $request->validated();

        if ($validated['role_id'] == 2) { // patient register
            $otp = OtpService::generateOtp();

            Cache::put('otp_' . $validated['phone_number'], [
                'otp' => $otp,
                'expires_at' => now()->addMinutes(5),
            ]);

            return response()->json(['otp' => $otp]);
        } else {
            // Create base user
            $user = User::create([
                'name' => $validated['name'], // raw value, will be translated if student
                'phone_number' => $validated['phone_number'],
                'national_number' => $validated['national_number'],
                'password' => Hash::make($validated['password']),
                'role_id' => $validated['role_id'],
            ]);

            if ($validated['role_id'] == 1) {

                $student = Student::create([
                    'user_id' => $user->id,
                    'year' => $request->year
                ]);

                // for translate name
                $tr = new GoogleTranslate();
                $tr->setTarget('en');
                $translated = $tr->translate($request->name);
                $sourceLang = $tr->getLastDetectedSource();
                if ($sourceLang === 'ur')
                    $sourceLang = 'ar';
                $targetLang = $sourceLang === 'ar' ? 'en' : 'ar';
                $tr->setSource($sourceLang)->setTarget($targetLang);
                $finalTranslation = $tr->translate($request->name);

                $user->update([
                    'name' => [
                        $sourceLang => $request->name,
                        $targetLang => $finalTranslation
                    ]
                ]);

                return response()->json([
                    'message' => 'Student registered successfully',
                    'student' => [
                        'student_id'=> $student->id,
                        'user_id'=> $student->user->id,
                        'role_id' => $student->user->role_id,
                        'name' => $student->user->name['en'],
                        'phone_number' => $student->user->phone_number,
                        'national_number' => $student->user->national_number,
                        'year'=> __('student_year.' . $student->year, [], 'en'),
                        'profile_image_url'=> $student->profile_image_url,
                        ]
                    ]);
            }

            return response()->json([
                'message' => 'User registered successfully',
                'user' => $user
            ], 201);
        }
    }

    public function deleteUser($id)
    {
        validator(['id' => $id], [
            'id' => 'required|integer|exists:users,id'
        ])->validate();

        User::findOrFail($id)->delete();

        return response()->json([
            'status' => 'deleted success'
        ]);
    }

    public function addPracticalSchesdule(Request $request)
    {
        $validated = $request->validate([
            'days' => 'required|string|in:Sunday,Monday,Tuesday,Wednesday,Thursday',
            'stage_id' => 'required|exists:stages,id',
            'supervisor_id' => 'required|exists:users,id',
            'location' => 'required|string',
            'start_time' => 'required|date_format:H:i',
            'end_time' => 'required|date_format:H:i|after:start_time',
            'year' => 'required|string|in:fourth-year,fifth-year'
        ]);

        $schedule = PracticalSchedule::where($validated)->exists();

        if ($schedule) {
            $schedule->update($validated);

            return response()->json([
                'status' => 'updated',
                'schedule' => $schedule
            ]);
        }

        $schedule = PracticalSchedule::create($validated);

        return response()->json([
            'status' => 'success',
            'schedule' => $schedule
        ]);
    }

    public function viewYearSchedules(Request $request)
    {
        $request->validate([
            'year' => 'required|string|in:fourth-year,fifth-year',
        ]);

        $schedules = PracticalSchedule::where('year', $request->year)
            ->orderby('days')
            ->get()
            ->groupBy('days')
            ->map(function ($dayGroup) {
                return $dayGroup->map(function ($schedule) {
                    $stage = Stage::where('id',$schedule->stage_id)->first();
                    $supervisor = User::where('id',$schedule->supervisor_id)->first();
                    return [
                        'id' => $schedule->id,
                        'stage_name' => $stage->name['en'],
                        'supervisor_name' => $supervisor->name,
                        'location' => $schedule->location,
                        'start_time' => $schedule->start_time,
                        'end_time' => $schedule->end_time,
                    ];})->values();
            });

        return response()->json([
            'status' => 'success',
            'year' => $request->year,
            'schedules' => $schedules
        ]);
    }

    public function deleteSchedule($id)
    {
        validator(['id' => $id], [
            'id' => 'required|integer|exists:practical_schedules,id'
        ])->validate();

        PracticalSchedule::findOrFail($id)->delete();

        return response()->json([
            'status' => 'deleted success'
        ]);
    }

    public function addStage(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string',
            'required_case_count' => 'required|numeric'
            ]);

        $stage = Stage::where('name->en',$validated)->first();

        // for translate name
        $tr = new GoogleTranslate();
        $tr->setTarget('en');
        $translated = $tr->translate($request->name);
        $sourceLang = $tr->getLastDetectedSource();
        if ($sourceLang === 'ur')
            $sourceLang = 'ar';
        $targetLang = $sourceLang === 'ar' ? 'en' : 'ar';
        $tr->setSource($sourceLang)->setTarget($targetLang);
        $finalTranslation = $tr->translate($request->name);

        $validated['name']=[
                $sourceLang => $request->name,
                $targetLang => $finalTranslation
        ];

        if($stage){
            $stage->update($validated);
            return response()->json([
                'status' => 'updated',
                'stage' => [
                    "id" => $stage->id,
                    "name" => $stage->name['en'],
                    "required_case_count"=> $stage->required_case_count,
                ]
            ]);
        }

        $stage = Stage::create($validated);

        return response()->json([
            'status' => 'success',
            'stage' => [
                "id" => $stage->id,
                "name" => $stage->name['en'],
                "required_case_count"=> $stage->required_case_count,
            ]
        ]);
    }

    public function deleteStage($id)
    {
        validator(['id' => $id], [
            'id' => 'required|integer|exists:stages,id'
        ])->validate();

        Stage::findOrFail($id)->delete();

        return response()->json([
            'status' => 'deleted success'
        ]);
    }

    public function viewStages()
    {
        $stages = Stage::all()->map(function ($stage) {
            return [
                'id' => $stage->id,
                'name' => $stage->name['en'],
                'required_case_count' => $stage->required_case_count,
            ];
        });

        return response()->json([
            'status' => 'success',
            'stages' => $stages
        ]);
    }

    public function viewAllPatients()
    {
        $patients = Patient::paginate(10)
            ->through(function ($patient) {
                return [
                    'id' => $patient->id,
                    'name' => $patient->user->name,
                    'phone_number' => $patient->user->phone_number,
                    'age' => Carbon::parse($patient->birthdate)->age,
                ];
            });

        return response()->json([
            'status' => 'success',
            'patients' => $patients
        ]);
    }

    public function patientInfo(Request $request)
    {
        $request->validate([
            'patient_id' => 'required|exists:patients,id',
        ]);

        $patient = Patient::find($request->patient_id);

        $xrayImages = $patient->patientRequests
            ->flatMap(function ($req) {
                return $req->radiologyImages
                    ->where('type', 'x-ray')
                    ->select('image_url');
            })
            ->values();

        return response()->json([
            'status' => 'success',
            'user_id' => $patient->user->id,
            'name' => $patient->user->name,
            'phone_number' => $patient->user->phone_number,
            'birthdate' => $patient->birthdate,
            'age' => Carbon::parse($patient->birthdate)->age ,
            'weight' => $patient->weight,
            'height' => $patient->height,
            'diseases' => $patient->diseases->select('name'),
            'madicines' => optional($patient->medications->select('image_url')),
            'x-ray_images' => optional($xrayImages)
        ]);
    }

    public function viewAllStudents()
    {
        $students = Student::paginate(10)
            ->through(function ($student) {
                return [
                    'student_id' => $student->id,
                    'user_id' => $student->user->id,
                    'name' => $student->user->name['en'],
                    'profile_image' => $student->profile_image_url,
                    'phone_number' => optional($student->user)->phone_number ?? null,
                    'year' => __('student_year.' . $student->year, [], 'en'),
                ];
            });

        return response()->json([
            'status' => 'success',
            'students' => $students
        ]);
    }

    public function viewAllSupervisors()
    {
        $supervisors = User::where('role_id', 3)
            ->paginate(10)
            ->through(function ($supervisor) {
                return [
                    'id' => $supervisor->id,
                    'name' => $supervisor->name,
                    'phone_number' => optional($supervisor)->phone_number ?? null,
                ];
            });

        return response()->json([
            'status' => 'success',
            'supervisors' => $supervisors
        ]);
    }

    public function viewAllDoctors()
    {
        $doctors = User::where('role_id', 4)
            ->paginate(10)
            ->through(function ($doctor) {
                return [
                    'id' => $doctor->id,
                    'name' => $doctor->name,
                    'phone_number' => optional($doctor)->phone_number ?? null,
                ];
            });

        return response()->json([
            'status' => 'success',
            'supervisors' => $doctors
        ]);
    }

    public function statistics()
    {
        $students = Student::all()->count();
        $patients = Patient::all()->count();
        $supervisors = User::where('role_id',3)->count();
        $doctors = User::where('role_id',4)->count();
        $requests = PatientRequest::all()->count();

        return response()->json([
            'status' => 'success',
            'students' => $students,
            'patients' => $patients,
            'supervisors' => $supervisors,
            'doctors' => $doctors,
            'requests' => $requests
        ]);
    }

    public function todaySchedule()
    {
        $today = now()->englishDayOfWeek;

        $schedules = PracticalSchedule::where('days', $today)->get();

        return response()->json([
            'status' => 'success',
            'schedules' => $schedules
        ]);
    }

    public function addReinternship(Request $request)
    {
        $request->validate([
            'national_number' => 'required|exists:users,national_number',
            'stage_id' => 'required|exists:stages,id'
        ]);

        $user = User::where('national_number',$request->national_number)->first();

        if(!$user->student)
            return response()->json([
                'message' => 'failed , only students can application'
            ]);

        if(!PracticalSchedule::where('stage_id',$request->stage_id)->exists())
            return response()->json([
                'message' => 'failed , you cant submit an application with this internship this semester'
            ]);

        $reinternship = ReInternship::create([
            'student_id' => $user->student->id,
            'stage_id' => $request->stage_id,
        ]);

        return response()->json([
            'status' => 'success',
            'application' => [
                'student_name' => $user->name['en'],
                'stage_name' => Stage::find($request->stage_id)->name['en'],
                'status' => 'approved'
            ]
        ]);
    }

    public function viewAllReinternships()
    {
        $reinternships = ReInternship::paginate(10)
            ->through(function ($item) {
                $student = Student::find($item->student_id);
                return [
                    'student_id' => $item->student_id,
                    'student_name' => $student->user->name['en'],
                    'stage_name' => Stage::find($item->stage_id)->name['en'],
                ];
            });

        return response()->json([
            'status' => 'success',
            'reinternships' => $reinternships
        ]);
    }

    /////توزيع الطلاب على العملي والفئات
    /// رابعة
    public function distributeFourthYearStudents(StudentDistributionService $service)
    {
        $result = $service->distributeStudentsByYear('fourth-year');

        return response()->json(['message' => $result['message']], $result['success'] ? 200 : 400);
    }

    //خامسة
    public function distributeFifthYearStudents(StudentDistributionService $service)
    {
        $result = $service->distributeStudentsByYear('fifth-year');

        return response()->json(['message' => $result['message']], $result['success'] ? 200 : 400);
    }

}
