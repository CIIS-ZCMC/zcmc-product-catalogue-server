<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/**
 * Shared validation for creating (POST /users) and updating (PUT /users/{id}) a user.
 */
class UserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('email'))) {
            $this->merge(['email' => strtolower(trim($this->input('email')))]);
        }
    }

    public function rules(): array
    {
        $existing = $this->targetUser();
        $isCreate = $existing === null;

        $isAdmin = $this->has('is_admin')
            ? $this->boolean('is_admin')
            : (bool) $existing?->is_admin;

        $password = [
            'nullable',
            'string',
            'confirmed',
            Password::min(8)->numbers()->symbols(),
        ];

        return [
            'first_name' => 'required|string|max:100',
            'last_name' => 'required|string|max:100',
            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($existing?->id),
            ],
            'password' => $password,
            'is_admin' => [$isCreate ? 'required' : 'sometimes', 'boolean'],
            'is_active' => 'sometimes|boolean',
            'internal_category_ids' => $isAdmin ? 'nullable|array' : 'required|array|min:1',
            'internal_category_ids.*' => ['integer', 'distinct', 'exists:internal_categories,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'internal_category_ids.required' => 'Assign at least one internal category to an encoder.',
            'internal_category_ids.min' => 'Assign at least one internal category to an encoder.',
        ];
    }

    /**
     * The user being updated, or null when creating.
     */
    protected function targetUser(): ?User
    {
        $id = $this->route('id');

        return $id ? User::findOrFail($id) : null;
    }
}
