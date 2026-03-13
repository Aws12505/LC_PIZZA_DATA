<?php

namespace App\Http\Requests\DataEntry;

use Illuminate\Foundation\Http\FormRequest;
use App\Models\Tag;
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

            'paginated' => 'sometimes|boolean',
            'page' => 'sometimes|integer|min:1',
            'per_page' => 'sometimes|integer|min:1|max:100',

            'tags' => 'sometimes|string'
        ];
    }

    public function messages(): array
    {
        return [
            'from.required' => 'from is required.',
            'from.date_format' => 'from must be YYYY-MM-DD.',

            'to.required' => 'to is required.',
            'to.date_format' => 'to must be YYYY-MM-DD.',
            'to.after_or_equal' => 'to must be after or equal to from.',

            'paginated.boolean' => 'paginated must be true or false.',

            'page.integer' => 'page must be a number.',
            'page.min' => 'page must be at least 1.',

            'per_page.integer' => 'per_page must be a number.',
            'per_page.min' => 'per_page must be at least 1.',
            'per_page.max' => 'per_page cannot exceed 100.',

            'tags.string' => 'tags must be a comma separated list of tag ids.'
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

            $existingIds = Tag::whereIn('id', $tagIds)
                ->pluck('id')
                ->toArray();

            $invalidIds = array_diff($tagIds, $existingIds);

            if (!empty($invalidIds)) {
                $validator->errors()->add(
                    'tags',
                    'The following tag ids do not exist: ' . implode(',', $invalidIds)
                );
            }
        });
    }
}
