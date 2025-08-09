<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateProfileRequest extends FormRequest
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
    public function rules()
    {
        return [
            'name' => 'sometimes|string|max:255',
            'phone' => 'sometimes|string|unique:users,phone,' . auth()->id(),
            'national_number' => 'sometimes|string|unique:users,national_number,' . auth()->id(),
            'password' => 'sometimes|confirmed|min:8',

            // Patient-specific
            'birthdate' => 'sometimes|date',
            'weight' => 'sometimes|numeric|min:1',
            'height' => 'sometimes|numeric|min:1',

            // Diseases
            'disease_id' => 'sometimes|array',
            'disease_id.*' => 'exists:diseases,id',

            // Medications
            'medications' => 'sometimes|array',
            'medications.*.image_url' => 'required|string|url',
        ];
    }

}
