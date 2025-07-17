<?php

namespace App\Http\Controllers;

use App\Models\Patient;
use App\Models\RadiologyImage;
use App\Models\PatientRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class RadiologyController extends Controller
{
    public function uploadRadiologyImage(Request $request)
    {
        $request->validate([
            'image' => 'required|image|mimes:jpg,jpeg,png|max:8192',
            'type' => 'required|in:x-ray,real-image',
        ]);

        $patient = Patient::where('user_id', Auth::id())->first();

        $image = $request->file('image');
        $extension = $image->getClientOriginalExtension(); //ex: jpg
        $filename = 'patientId-' . $patient->id . '.' . $extension;
        $path = $image->storeAs('radiology', $filename, 'public');

        $req = PatientRequest::create([
            'patient_id' => $patient->id,
            'status' => 'under processing'
        ]);

        $radiologyImage = RadiologyImage::create([
            'request_id' => $req->id,
            'patient_id' => $patient->id,
            'image_url' => $path,
            'type' => $request->type,
        ]);

        return response()->json([
            'message' => 'Radiology image uploaded successfully',
            'url' => asset('storage/' . $path),
            'data' => $radiologyImage,
        ]);
    }
}
