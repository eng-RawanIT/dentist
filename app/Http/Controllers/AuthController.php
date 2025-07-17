<?php

namespace App\Http\Controllers;

use App\Http\Requests\LoginRequest;
use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateProfileRequest;
use App\Models\Student;
use App\Models\User;
use App\Services\OtpService;
use App\Services\SMSService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class AuthController extends Controller
{

    public function sms(){
        $otp = OtpService::generateOtp();
        $phone = "+963991402099";
        $smsService = new SMSService();
        $r = $smsService->sendSMS($phone, $otp);
        return $r;
    }

    public function AddUser (StoreUserRequest $request)
    {
        $validated = $request->validated();

        if($validated['role_id']== 2){ //patient register

            $otp = OtpService::generateOtp();

            //$smsService = new SMSService();
            //$smsService->sendSMS($user->phone_number, $otp);

            Cache::put('otp_' . $validated['phone_number'], [
                'otp' => $otp,
                'expires_at' => now()->addMinutes(5),
            ]);

            return response()->json(['otp'=>$otp]);
        }
        else {
            $user = User::create([
                'name' => $validated['name'],
                'phone_number' => $validated['phone_number'],
                'national_number' => $validated['national_number'],
                'password' => Hash::make($validated['password']),
                'role_id' => $validated['role_id'],
            ]);
            if($validated['role_id']== 1){
                Student::create([
                  'user_id' => $user->id,
                  'year' => $validated['year']
                ]);
            }

            return response()->json(['message' => 'User registered successfully'], 201);
        }
    }


    public function login(LoginRequest $request)
    {
        $validated = $request->validated();

        if (isset($validated['national_number'])) {
            $user = User::where('national_number', $validated['national_number'])->first();
        } else {
            $user = User::where('phone_number', $validated['phone_number'])->first();
        }

        // Check credentials
        if (!$user || !Hash::check($validated['password'], $user->password)) {
            return response()->json(['message' => 'Invalid credentials'], 401);
        }

        // Generate token
        $token = $user->createToken('authToken')->plainTextToken;
        $role = DB::table('roles')
            ->where('id', $user->role_id)->first();

        // if it student return complete information
        if($user->role_id == 1){
            $user->load('student');
            return response()->json([
                'role_name' =>$role->name,
                'user' => $user,
                'token' => $token,
            ]);
        }

        return response()->json([
            'role_name' =>$role->name,
            'user' => $user,
            'token' => $token,
        ]);
    }


    public function logout(Request $request)
    {
        $request->user()->tokens()->delete();
        return response()->json(['message' => 'Logged out successfully'], 201);
    }

    // Send OTP for Password Reset
    public function sendOtp(Request $request)
    {
        $request->validate([
            'phone_number' => 'required|numeric|exists:users,phone_number',
        ]);

        $user = User::where('phone_number', $request->phone_number)->firstOrFail();

        $otp = OtpService::generateOtp();
        $user->update([
            'otp' => $otp,
            'otp_expires_at' => Carbon::now()->addMinutes(5),
        ]);

        //$smsService = new SMSService();
        //$smsService->sendSMS($user->phone_number, $otp);

        return response()->json(['message' => 'OTP sent successfully' , 'otp' => $otp], 201);
    }

    // Users Verify OTP
    public function verifyOtp(Request $request)
    {
        $request->validate([
            'phone_number' => 'required|numeric|exists:users,phone_number',
            'otp' => 'required|numeric|digits:4'
        ]);

        $user = User::where('phone_number', $request->phone_number)->first();

        if (!$user || $user->otp !== $request->otp || Carbon::now()->gt($user->otp_expires_at)) {
            return response()->json(['message' => 'Invalid or expired OTP'], 401);
        }

        $user->update(['otp' => null, 'otp_expires_at' => null]);

        return response()->json(['message' => 'OTP verified'],201);
    }

    //patient verify otp
    public function patientVerifyOtp(Request $request){

        $request->validate([
            'otp' => 'required|numeric|digits:4',
            'name' => 'required|string',
            'phone_number' => 'required|numeric|unique:users,phone_number',
            'password' => 'required|string|min:8|max:12',
        ]);

        $cached = Cache::get('otp_' . $request->phone_number);
        if (!$cached || $cached['otp'] != $request->otp || now()->gt($cached['expires_at'])) {
            return response()->json(['message' => 'Invalid or expired OTP'], 401);
        }

        DB::beginTransaction();
        $user = User::create([
            'name' => $request->name,
            'phone_number' => $request->phone_number,
            'password' => Hash::make($request->password),
            'role_id' => 2
        ]);
        $token = $user->createToken('authToken')->plainTextToken;
        Cache::forget('otp_' . $request->phone_number);
        DB::commit();

        return response()->json([ 'patient'=> $user , 'token'=> $token],201);
    }

    // Reset Password after OTP
    public function resetPassword(Request $request)
    {
        $request->validate([
            'phone_number' => 'required|numeric|exists:users,phone_number',
            'password' => 'required|string|min:8|max:12|confirmed',
        ]);

        $user = User::where('phone_number', $request->phone_number)->firstOrFail();

        if(Hash::check($request->password,$user->password)) {
            return response()->json(['message' => 'it is the same old password , try again'], 401);
        }else {
            $user->update([
                'password' => Hash::make($request->password)
            ]);
            return response()->json(['message' => 'Password reset successfully'], 201);
        }
    }

    /*public function updateProfile(UpdateProfileRequest $request)
    {
        $user = Auth::user();

        // 1. Update User table
        $user->fill($request->only(['name', 'phone', 'national_number']));

        if ($request->filled('password')) {
            $otp = OtpService::generateOtp();
            $user->update([
                'otp' => $otp,
                'otp_expires_at' => Carbon::now()->addMinutes(5),
            ]);
            //$smsService = new SMSService();
            //$smsService->sendSMS($user->phone_number, $otp);
            $user->password = Hash::make($request->password);
            return response()->json(['otp' => $otp]);
        }

        if($request->filled('phone_number')){
            $otp = OtpService::generateOtp();
            $user->update([
                'otp' => $otp,
                'otp_expires_at' => Carbon::now()->addMinutes(5),
            ]);
            //$smsService = new SMSService();
            //$smsService->sendSMS($user->phone_number, $otp);
            $user->password = Hash::make($request->password);
            return response()->json(['otp' => $otp]);
        }

        $user->save();

        // 2. Update Patient-specific data
        if ($user->role_id = 2 && $user->patient) {
            $patient = $user->patient;
            $patient->fill($request->only(['birth_date', 'weight', 'height']));
            $patient->save();

            // 3. Sync Diseases (many-to-many)
            if ($request->has('disease_ids')) {
                $patient->diseases()->sync($request->disease_ids);
            }

            // 4. Update Medications
            if ($request->has('medications')) {
                $patient->medications()->delete(); // Clear existing
                foreach ($request->medications as $med) {
                    $patient->medications()->create([
                        'image_url' => $med['image_url'],
                    ]);
                }
            }
        }

        return response()->json(['message' => 'Profile updated successfully.']);
    }*/
}
