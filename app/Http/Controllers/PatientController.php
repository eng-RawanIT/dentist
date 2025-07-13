<?php

namespace App\Http\Controllers;

use App\Models\MedicationImage;
use App\Models\Patient;
use App\Models\PatientRequest;
use App\Models\PracticalSchedule;
use App\Models\Stage;
use App\Models\Student;
use App\Models\User;
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
        $validatedData['birthdate'] = \Carbon\Carbon::createFromFormat('d-m-Y', $validatedData['birthdate'])->format('Y-m-d');

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
    public function oralMedicineDentist()
    {

        $students = Student::whereHas('practicalSchedule', function ($query) {
            $query->where('stage_id', 3);
        })->with(['user', 'appointments.stage', 'appointments.session'])
            ->get()
            ->unique('national_number')
            ->values();

        $result = $students->map(function ($student) {
            // Filter appointments for stage_id = 3
            $stage = $student->appointments->filter(function ($appointment) {
                return $appointment->stage_id == 3 && $appointment->session;
            });
            // Get sessions from filtered appointments and calculate average evaluation
            $avgEvaluation = $stage->pluck('session')
                ->pluck('evaluation_score')
                ->avg();

        /*$result = $students->map(function ($student) {
            // Get all sessions from student's appointments
            $sessions = $student->appointments->pluck('session')->filter();
            $avgEvaluation = $sessions->avg('evaluation_score');*/

            return [
                'id' => $student->id,
                'name' => $student->user->name,
                'year' => $student->year,
                'profile_image' => $student->profile_image_url,
                'avg_evaluation' => round($avgEvaluation, 2),
            ];
        });

        return response()->json([
            'status' => 'success',
            'students' => $result
        ]);
    }

    public function requestStatus()
    {
        $patient = Patient::where('user_id', Auth::id())->firstOrFail();

        // Get the latest request for this patient
        $latestRequest = $patient->patientRequests()->latest()->first();

        // Case 1: No request yet
        if (!$latestRequest) {
            return response()->json([
                'id' => 1,
                'status' => 'Please visit the radiology department first.'
            ]);
        }

        // Case 2: Request under processing
        if ($latestRequest->status === 'under processing') {
            return response()->json([
                'id' => 2,
                'status' => 'Your request is currently under processing.'
            ]);
        }

        // Case 3: Request is processed → find matching students
        if ($latestRequest->status === 'processed') {
            $stageId = $latestRequest->stage_id;

            $scheduleIds = PracticalSchedule::where('stage_id', $stageId)->pluck('id');

            $studentIds = DB::table('student_schedule_pivot')
                ->whereIn('practical_schedule_id', $scheduleIds)
                ->pluck('student_id');

            $students = Student::with(['user:id,name']) // only load user's name
            ->whereIn('id', $studentIds)
                ->select('id', 'user_id', 'year') // only needed student columns
                ->get()
                ->map(function ($student) {
                    // Calculate average evaluation from all sessions
                    $avg = $student->appointments()
                        ->whereHas('session') // only appointments with sessions
                        ->with('session')
                        ->get()
                        ->pluck('session.evaluation_score')
                        ->filter() // remove nulls
                        ->avg();

                    return [
                        'id' => $student->id,
                        'name' => $student->user->name,
                        'year' => $student->year,
                        'avg_evaluation' => $avg ? round($avg, 2) : null,
                    ];
                });

            return response()->json([
                'id' => 3,
                'status' => 'success',
                'stage_id' => $stageId,
                'stage_name' => Stage::find($stageId)->name,
            'students' => $students
            ]);
        }
    }

    /*
    public function uploadMedicationImage(Request $request)
    {
        $request->validate([
            'image' => 'required|image|mimes:jpg,jpeg,png|max:4096',
        ]);

        $patient = Patient::where('user_id', Auth::id())->first();

        $image = $request->file('image');
        $extension = $image->getClientOriginalExtension(); //ex: jpg
        $filename = 'patientId-' . $patient->id . '.' . $extension;
        $path = $image->storeAs('medications', $filename, 'public');

        $medicationImage = MedicationImage::create([
            'patient_id' => $patient->id,
            'image_url' => $path,
        ]);

        return response()->json([
            'message' => 'Medication image uploaded successfully',
            'url' => asset('storage/' . $path),
            'data' => $medicationImage,
        ]);
    }
    public function storeDiseases(Request $request)
    {
        $request->validate([
            'disease_id' => 'required|array',
            'disease_id.*' => 'exists:diseases,id',
        ]);

        $patient = Patient::where('user_id', Auth::id())->firstOrFail();

        $patient->diseases()->sync($request->disease_id); //attach

        return response()->json([
            'message' => 'Diseases saved successfully',
        ]);
    }
*/
}
