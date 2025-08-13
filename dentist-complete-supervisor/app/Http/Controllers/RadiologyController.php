<?php

namespace App\Http\Controllers;

use App\Http\Requests\AddPatientRequest;
use App\Models\Disease;
use App\Models\Patient;
use App\Models\RadiologyImage;
use App\Models\PatientRequest;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class RadiologyController extends Controller
{
    public function uploadRadiologyImage(Request $request)
    {
        $request->validate([
            'image' => 'required|image|mimes:jpg,jpeg,png|max:8192',
            'type' => 'required|in:x-ray,real-image',
            'patient_id' => 'required|exists:patients,id'
        ]);

        $image = $request->file('image');
        $extension = $image->getClientOriginalExtension(); //ex: jpg

        $req = PatientRequest::create([
            'patient_id' => $request->patient_id,
            'stage_id' => null,
            'status' => 'under processing'
        ]);

        $filename = 'requestId-' . $req->id . '-patientId-' . $request->patient_id . '.' . $extension;
        $path = $image->storeAs('radiology', $filename, 'public');

        $radiologyImage = RadiologyImage::create([
            'request_id' => $req->id,
            'image_url' => $path,
            'type' => $request->type,
        ]);

        return response()->json([
            'message' => 'Radiology image uploaded successfully',
            'url' => asset('storage/' . $path),
            'data' => $radiologyImage,
        ]);
    }

    public function addPatient(AddPatientRequest $request)
    {
        $validated = $request->validated();

        $user = User::create([
            'name' => $validated['name'],
            'phone_number' => $validated['phone_number'],
            'password' => Hash::make($validated['password']),
            'role_id' => 2
        ]);

        $patient = Patient::create([
            'user_id' => $user->id,
            'height' => $validated['height'],
            'weight' => $validated['weight'],
            'birthdate' => Carbon::createFromFormat('d-m-Y', $validated['birthdate'])->format('Y-m-d'),
        ]);

        $patient->diseases()->sync($validated['disease_id']);

        return response()->json([
            'status' => 'add successfully',
            'patient_id' => $patient->id
        ]);
    }

    public function searchPatient(Request $request)
    {
        $request->validate([
            'phone_number' => 'required|numeric|digits:9|exists:users,phone_number',
        ]);

        $user = User::where('phone_number',$request->phone_number)->first();

        if(!$user->patient)
            return response()->json([
                'status' => 'the patient didnt complete her registration, should enter here information',
            ]);

        $patient = Patient::find($user->patient->id);
        return response()->json([
            'status' => 'success',
            'patient_id' => $user->patient->id,
            'name' => $user->name,
            'phone_number' => $request->phone_number,
            'age' => Carbon::parse($user->patient->birthdate)->age
        ]);
    }

    public function allDiseases()
    {
        $diseases = Disease::all();
        return response()->json([
            'status' => 'success',
            'diseases' => $diseases->select('id','name')
        ]);
    }

    public function radiologyStats()
    {
        $today = Carbon::today();
        $startOfWeek = $today->copy()->startOfWeek(Carbon::SUNDAY);

        $totalPatients = Patient::count();

        $imagesThisWeek = RadiologyImage::whereBetween('created_at', [$startOfWeek, $today->copy()->endOfDay()])->count();

        $newPatientsToday = Patient::whereBetween('created_at', [$startOfWeek, $today->copy()->endOfDay()])->count();

        $pendingRequests = PatientRequest::whereDate('created_at', $today)
            ->where('status', 'under processing')
            ->count();

        return response()->json([
            'status' => 'success',
            'total_patients' => $totalPatients,
            'images_this_week' => $imagesThisWeek,
            'new_patients' => $newPatientsToday,
            'pending_requests' => $pendingRequests
        ]);
    }

    public function recentImages()
    {
        $today = Carbon::today();
        $startOfWeek = $today->copy()->startOfWeek(Carbon::SUNDAY);

        $images = RadiologyImage::whereBetween('created_at', [$startOfWeek, $today->copy()->endOfDay()])
            ->where('type','x-ray')
            ->get();

        return response()->json([
            'status' => 'success',
            'images_this_week' => $images
        ]);
    }
}
