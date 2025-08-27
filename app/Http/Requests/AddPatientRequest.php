<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AddPatientRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => 'required|string',
            'phone_number' => 'required|numeric|digits:9|unique:users,phone_number',
            'password' => 'required|string|min:8|max:12',
            'height' => 'required|numeric|min:0',
            'weight' => 'required|numeric|min:0',
            'birthdate' => 'required|date|before:today|date_format:d-m-Y',
            'disease_id' => 'required|array',
            'disease_id.*' => 'exists:diseases,id',
        ];
    }
}
