<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RoleSelectionRequest extends FormRequest
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
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array|string>
     */
    public function rules(): array
    {
        return [
            'role' => ['required', 'string', 'in:student,lecturer,administrator'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'role.required' => 'Please select a role.',
            'role.in' => 'Please select a valid role.',
        ];
    }
}
