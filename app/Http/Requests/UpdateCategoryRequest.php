<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $key = $this->route('key');

        return [
            'title' => 'required|string|max:500',
            'code' => 'nullable|string|max:50',
            'parent_key' => [
                'nullable',
                'exists:categories,key',
                function ($attribute, $value, $fail) use ($key) {
                    if ($value == $key) {
                        $fail('A category cannot be its own parent.');
                    }
                },
            ],
        ];
    }
}
