<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreUserRequest extends FormRequest
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
            'phone_number' => 'nullable|numeric|digits:9|unique:users,phone_number',
            'national_number' => 'required_unless:role_id,2|numeric|digits:11|unique:users,national_number',
            'password' => 'required|string|confirmed|min:8|max:12',
            'role_id' => 'required|string|exists:roles,id', // example: 'patient:2'
            'year' => 'sometimes|required|string|in:fourth-year,fifth-year' //if it is student
        ];
    }
}
