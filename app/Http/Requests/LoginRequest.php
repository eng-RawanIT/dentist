<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class LoginRequest extends FormRequest
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
            'password' => 'required|string|min:8|max:12',
            'national_number' => 'sometimes|required|numeric|digits:11|exists:users,national_number',
            'phone_number' => 'sometimes|required|numeric|digits:9|exists:users,phone_number',
        ];
    }

    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            $data = $this->all();

            if (empty($data['national_number']) && empty($data['phone_number'])) {
                $validator->errors()->add('login', 'Either national number or phone number is required.');
            }

            if (!empty($data['national_number']) && !empty($data['phone_number'])) {
                $validator->errors()->add('login', 'Only one of national number or phone number should be provided.');
            }
        });
    }

}
