<?php

namespace App\Http\Controllers;

use App\Models\MedicationImage;
use App\Models\Patient;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PatientController extends Controller
{

    public function storeInformation (Request $request)
    {
        $validatedData = $request->validate([
            'gender'    => 'required|in:male,female',
            'height'    => 'required|numeric|min:0',
            'weight'    => 'required|numeric|min:0',
            'birthdate' => 'required|date|before:today|date_format:d-m-Y',
        ]);

        // Convert birthdate to database format (Y-m-d)
        $validatedData['birthdate'] = \Carbon\Carbon::createFromFormat('d-m-Y', $validatedData['birthdate'])->format('Y-m-d');

        $patient = Patient::create([
                'user_id' => Auth::id(),
            ] + $validatedData);

        return response()->json([
            'message' => 'Patient information stored successfully.',
            'patient' => $patient
        ], 201);
    }

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

}
