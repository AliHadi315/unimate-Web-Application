<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreLectureRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'course_id'   => ['required', 'integer', 'exists:courses,id'],
            'day_of_week' => ['required', 'integer', 'between:0,6'],
            'start_time'  => ['required', 'date_format:H:i'],
            'end_time'    => ['required', 'date_format:H:i', 'after:start_time'],
            'room'        => ['nullable', 'string', 'max:60'],
        ];
    }

    public function messages(): array
    {
        return [
            'course_id.required'   => 'Please select a course.',
            'day_of_week.required' => 'Please pick a day.',
            'start_time.required'  => 'Start time is required.',
            'end_time.required'    => 'End time is required.',
            'end_time.after'       => 'The class must end after it starts.',
        ];
    }
}
