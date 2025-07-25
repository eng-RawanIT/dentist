<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class sessionRequest extends FormRequest
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
            'appointment_id' => 'required|exists:appointments,id',
            'description' => 'required|string',
            'images' => 'required|array',
            'images.*.file' => 'required|image|mimes:jpg,jpeg,png|max:4096',
            'images.*.type' => 'required|in:before-treatment,after-treatment',
        ];
    }
}
