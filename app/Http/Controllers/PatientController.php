<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\AvailableAppointment;
use App\Models\MedicationImage;
use App\Models\Patient;
use App\Models\PatientRequest;
use App\Models\PracticalSchedule;
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
        $years = PracticalSchedule::where('stage_id', 3)
            ->pluck('year')
            ->unique()
            ->toArray();

        $students = Student::whereIn('year', $years)
            ->with(['user', 'appointments.stage', 'appointments.session'])
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

        // Latest request
        $latestRequest = $patient->patientRequests()->latest()->first();

        // Case 1: No request yet
        if (!$latestRequest) {
            return response()->json([
                'id' => 1,
                'status' => 'Please visit the radiology department first.'
            ]);
        }

        // Case 2: Under processing
        if ($latestRequest->status === 'under processing') {
            return response()->json([
                'id' => 2,
                'status' => 'Your request is currently under processing.'
            ]);
        }

        // Case 3: Processed
        if ($latestRequest->status === 'processed') {
            $stageId = $latestRequest->stage_id;

            // Get years that have this stage
            $eligibleYears = PracticalSchedule::where('stage_id', $stageId)
                ->pluck('year')
                ->unique()
                ->toArray();

            // Find students in those years
            $students = Student::whereIn('year', $eligibleYears)
                ->with(['user:id,name', 'appointments.session'])
                ->select('id', 'user_id', 'year', 'profile_image_url')
                ->get()
                ->map(function ($student) {
                    $avg = $student->appointments
                        ->pluck('session')
                        ->filter()
                        ->pluck('evaluation_score')
                        ->avg();

                    return [
                        'id' => $student->id,
                        'name' => $student->user->name,
                        'year' => $student->year,
                        'profile_image' => $student->profile_image_url,
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


    public function viewAvailableAppointmentss(Request $request)
    {
        $request->validate([
            'student_id' => 'required|exists:students,id',
            'stage_id' => 'required|exists:stages,id',
        ]);

        $availableAppointments = AvailableAppointment::where('student_id', $request->student_id)
            ->where('stage_id', $request->stage_id)
            ->where('status', 'on') // only active
            ->whereDate('date', '>=', now())
            ->orderBy('date')
            ->orderBy('time')
            ->get();

        // Group by date and format each group
        $grouped = $availableAppointments
            ->groupBy('date')
            ->map(function ($items, $date) {
                return [
                    'date' => $date,
                    'day' => Carbon::parse($date)->format('l'),
                    'status' => 'on',
                    'times' => $items->map(function ($item) {
                        return [
                            'id' => $item->id,
                            'time' => $item->time,
                        ];})->values(),
                ];})->values();

        return response()->json([
            'status' => 'success',
            'available_appointments' => $grouped
        ]);
    }

    public function viewAvailableAppointments(Request $request)
    {
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
        $response = $weekDays->map(function ($day) use ($groupedByDate, $nextWeekDays) {
            $date = $nextWeekDays[$day];
            $appointments = $groupedByDate->get($date, collect());

            return [
                'date' => $appointments->isNotEmpty() ? $date : null,
                'day' => $day,
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
            'patient_id' => $patient->id,
            'student_id' => $available->student_id,
            'stage_id'   => $available->stage_id,
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
