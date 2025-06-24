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

        $patient = Patient::where('user_id', Auth::id())->first();
        if(!$patient)
        {
        Patient::create([
                'user_id' => Auth::id(),
            ] + $validatedData);
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
        $schedules = PracticalSchedule::where('stage_id', 3)->get();
        $students = $schedules->flatMap(function ($schedule) {
            return $schedule->students;
        })->unique('national_number');

        return response()->json([
            'status' => 'successfully',
            'students'=> $students
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
