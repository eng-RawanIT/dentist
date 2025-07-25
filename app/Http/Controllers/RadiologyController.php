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
}
