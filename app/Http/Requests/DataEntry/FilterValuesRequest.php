<?php

namespace App\Http\Requests\DataEntry;

use Illuminate\Foundation\Http\FormRequest;
use App\Models\Tag;
class FilterValuesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'key_id' => 'sometimes|integer',
            'label' => 'sometimes|string|max:255',
            'data_type' => 'sometimes|in:text,number,decimal,boolean,json',

            'date' => 'sometimes|date_format:Y-m-d',
            'from' => 'sometimes|date_format:Y-m-d',
            'to' => 'sometimes|date_format:Y-m-d',

            'frequency_type' => 'sometimes|in:daily,weekly,monthly,yearly',
            'interval' => 'sometimes|integer|min:1',

            'due_on' => 'sometimes|date_format:Y-m-d',

            'tags' => 'sometimes|string',

            'page' => 'sometimes|integer|min:1',
            'per_page' => 'sometimes|integer|min:1|max:200',
        ];
    }

    public function messages(): array
    {
        return [
            'date.date_format' => 'date must be YYYY-MM-DD.',
            'from.date_format' => 'from must be YYYY-MM-DD.',
            'to.date_format' => 'to must be YYYY-MM-DD.',
            'interval.min' => 'interval must be >= 1.',
            'per_page.max' => 'per_page max is 200.',
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {

            $tags = $this->input('tags');

            if (!$tags) {
                return;
            }

            $tagIds = array_filter(
                explode(',', $tags),
                fn($v) => is_numeric($v)
            );

            if (empty($tagIds)) {
                $validator->errors()->add(
                    'tags',
                    'tags must contain valid numeric tag ids.'
                );
                return;
            }

            $existing = Tag::whereIn('id', $tagIds)->pluck('id')->toArray();

            $invalid = array_diff($tagIds, $existing);

            if (!empty($invalid)) {
                $validator->errors()->add(
                    'tags',
                    'The following tag ids do not exist: ' . implode(',', $invalid)
                );
            }
        });
    }
}
