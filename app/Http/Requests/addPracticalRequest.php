<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class addPracticalRequest extends FormRequest
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
        $isUpdate = $this->has('id');
        return [
            'id' => ['sometimes', 'exists:practical_schedules,id'],
            'days' => [$isUpdate ? 'sometimes' : 'required', 'string', 'in:Sunday,Monday,Tuesday,Wednesday,Thursday'],
            'stage_id' => [$isUpdate ? 'sometimes' : 'required', 'exists:stages,id'],
            'supervisor_id' => [$isUpdate ? 'sometimes' : 'required', 'exists:users,id'],
            'location' => [$isUpdate ? 'sometimes' : 'required', 'string'],
            'start_time' => [$isUpdate ? 'sometimes' : 'required', 'date_format:H:i'],
            'end_time' => [$isUpdate ? 'sometimes' : 'required', 'date_format:H:i', 'after:start_time'],
            'year' => [$isUpdate ? 'sometimes' : 'required', 'string', 'in:fourth-year,fifth-year'],
        ];
    }
}
