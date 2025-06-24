<?php

namespace App\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Psr7\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;


class SMSService
{
    protected $client;
    protected $apiKey;
    protected $apiUrl;

    public function __construct()
    {
        $this->client = new Client();
        $this->apiKey = "51577f8a-18df-4c7e-a10c-acee03ee2a79";
        $this->apiUrl ="http://192.168.137.84:8082/";
    }

    public function sendSMS($phone, $otp)
    {


        $headers = ['Authorization' => '51577f8a-18df-4c7e-a10c-acee03ee2a79',
            'Content-Type' => 'application/json'];

        //$body = '{ "to": "' . $phone . '", "message": "Your verification code is: ' . $otp . '" }';
        //$body = ['to' => $phone,'message' => 'Your verification code is: ' . $otp,];
        //$body = json_encode($body);
        //return $body;
        //$body = '{"to": "+963985797431","message": "Testing message101"}';

        $phone = trim($phone);
        $message = trim('Your verification code is: ' . $otp);
        $body = [
            'to' => $phone,
            'message' => $message,
        ];
        //$jsonBody = json_encode($body);
        //return $jsonBody;

        $json = json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $response = $this->client->request('POST', $this->apiUrl, [
            'headers' => [
                'Authorization' => $this->apiKey,
                'Content-Type' => 'application/json',
            ],
            'body' => $json,
        ]);
        return $response->getBody()->getContents();

        /*try{
        $request = new PatientRequest('POST', $this->apiUrl, $headers, $body);
        $res = $this->client->send($request); // not async
        return $res->getBody()->getContents();

        } catch (RequestException $e) {
        Log::error("SMS failed: " . $e->getMessage());
        throw new \Exception('Failed to send SMS: ' . $e->getMessage());
        }*/
    }
}
