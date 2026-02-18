<?php

namespace App\Http\Requests\DataEntry;

use Illuminate\Foundation\Http\FormRequest;

class DueRangeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'from' => 'required|date_format:Y-m-d',
            'to' => 'required|date_format:Y-m-d|after_or_equal:from',
        ];
    }

    public function messages(): array
    {
        return [
            'from.required' => 'from is required.',
            'to.required' => 'to is required.',
            'to.after_or_equal' => 'to must be after or equal to from.',
        ];
    }
}
