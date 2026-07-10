<?php
namespace App\Http\Requests;
use Illuminate\Foundation\Http\FormRequest;

class RoleSelectionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'role' => ['required', 'string', 'in:student,lecturer'],
        ];
    }

    public function messages(): array
    {
        return [
            'role.required' => 'Please select a role.',
            'role.in' => 'Please select a valid role.',
        ];
    }
}