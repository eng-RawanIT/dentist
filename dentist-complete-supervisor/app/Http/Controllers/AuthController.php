<?php

namespace App\Http\Controllers;

use App\Http\Requests\LoginRequest;
use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateProfileRequest;
use App\Models\Student;
use App\Models\User;
use App\Services\EditProfileService;
use App\Services\OtpService;
use App\Services\SMSService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Role;
use Stichoza\GoogleTranslate\GoogleTranslate;

class AuthController extends Controller
{

    public function sms(){
        $otp = OtpService::generateOtp();
        $phone = "+963991402099";
        $smsService = new SMSService();
        $r = $smsService->sendSMS($phone, $otp);
        return $r;
    }


    public function login(LoginRequest $request)
    {
        $validated = $request->validated();

        // for translation
        $lang = $request->header('Accept-Language', app()->getLocale()) ?? null;


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
            $user->name = $user->name[$lang] ?? 'null';
            $user->student->year = __('student_year.' . $user->student->year, [], $lang);
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


    /////////////////////////////////////////////////////////////// for profile update
    protected $editProfileService;

    public function __construct(EditProfileService $editProfileService)
    {
        $this->editProfileService = $editProfileService;
    }
    // for phone number
    public function sendEditOtp()
    {
        $user = Auth::user();
        $otp = $this->editProfileService->sendOtp($user);

        return response()->json(['message' => 'OTP sent successfully', 'otp' => $otp], 201);
    }

    public function verifyEditOtp(Request $request)
    {
        $request->validate(['otp' => 'required|numeric|digits:4']);
        $user = Auth::user();

        if (!$this->editProfileService->verifyOtp($user, $request->otp)) {
            return response()->json(['message' => 'Invalid or expired OTP'], 401);
        }

        return response()->json(['message' => 'OTP verified'], 201);
    }

    public function editUserProfile(Request $request)
    {
        $user = Auth::user();

        $validated = $request->validate([
            'name' => 'nullable|string|max:255',
            'phone_number' => 'nullable|numeric|exists:users,phone_number',
            'phone_number_for_password' => 'nullable|numeric|exists:users,phone_number',
            'password' => 'nullable|string|min:8|confirmed',
            'language' => 'nullable|string|in:en,ar' // for student
        ]);

        $lang = $validated['language'] ?? 'en';
        unset($validated['language']);

        try{
        $updatedUser = $this->editProfileService->updateProfile($user, $validated, $lang);

        return response()->json([
            'status' => 'success',
            'message' => 'User profile updated successfully',
            'user' => $updatedUser
        ]);

        } catch (\Exception $e) {
            return response()->json([
            'status' => 'error',
            'message' => $e->getMessage()
            ], 400);
            }
    }
}
