<?php

namespace App\Services;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Stichoza\GoogleTranslate\GoogleTranslate;

class EditProfileService
{

    public function sendOtp(User $user)
    {
        $otp = OtpService::generateOtp();
        $user->update([
            'otp' => $otp,
            'otp_expires_at' => Carbon::now()->addMinutes(5),
        ]);

        //$smsService = new SMSService();
        //$smsService->sendSMS($user->phone_number, $otp);

        return $otp; // For testing, in prod remove this
    }

    public function verifyOtp(User $user, string $otp): bool
    {
        if ($user->otp !== $otp || Carbon::now()->gt($user->otp_expires_at)) {
            return false;
        }

        $user->update([
            'otp' => null,
            'otp_expires_at' => null
        ]);

        return true;
    }

    public function updateProfile(User $user, array $data, string $lang)
    {
        if ($user->role_id == 1 && isset($data['name'])) {
            $tr = new GoogleTranslate();

            if ($lang === 'en') {
                $tr->setSource('en')->setTarget('ar');
                $translatedName = $tr->translate($data['name']);
                $data['name'] = [
                    'en' => $data['name'],
                    'ar' => $translatedName
                ];
            } elseif ($lang === 'ar') {
                $tr->setSource('ar')->setTarget('en');
                $translatedName = $tr->translate($data['name']);
                $data['name'] = [
                    'en' => $translatedName,
                    'ar' => $data['name']
                ];
            }

            $user->name = $data['name'];

        } elseif (isset($data['name']))
            $user->name = $data['name'];

        if (isset($data['phone_number']))
            $user->phone_number = $data['phone_number'];

        if (!empty($data['password'])) {
            if (empty($data['phone_number_for_password']) || $data['phone_number_for_password'] !== $user->phone_number)
                throw new \Exception('Phone number does not match. Cannot update password.');

            $user->password = Hash::make($data['password']);
        }

        $user->save();
        $user->name = $user->name[$lang];
        return $user;
        }
}

