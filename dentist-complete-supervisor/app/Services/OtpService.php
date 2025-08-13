<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class OtpService
{
    public static function generateOtp()
    {
        return rand(1000, 9999);
    }

    public static function sendOtp($phoneNumber, $otp)
    {
        // مثال: هنا تستخدمي أي API مزود SMS مثل Twilio أو غيره
        // مثلا مع Twilio:
        /*
        $response = Http::withBasicAuth('ACCOUNT_SID', 'AUTH_TOKEN')->post('https://api.twilio.com/2010-04-01/Accounts/ACCOUNT_SID/Messages.json', [
            'From' => 'TWILIO_PHONE_NUMBER',
            'To' => $phoneNumber,
            'Body' => "رمز التحقق الخاص بك هو: $otp",
        ]);
        */

        // أو حاليا بسجله باللوج للتجربة
        \Log::info("Sending OTP $otp to phone $phoneNumber");
    }
}

