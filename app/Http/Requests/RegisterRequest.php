<?php
namespace App\Http\Requests;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $rules = [
            'full_name' => ['required', 'string', 'min:3', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:User,Email'],
            // 'username' => ['required', 'string', 'min:3', 'max:255', 'unique:User,Username', 'alpha_dash'],
            'username' => ['required', 'string', 'min:3', 'max:255', 'unique:User,Handle', 'alpha_dash'],
            'password' => [
                'required',
                'string',
                'min:8',
                'confirmed',
                Password::min(8)->mixedCase()->numbers()->symbols(),
            ],
            'password_confirmation' => ['required', 'string', 'min:8'],
            'rules_accepted' => ['required', 'accepted'],
        ];

        if (session('registration_role') === 'lecturer') {
            $rules['staff_id_number'] = ['required', 'string', 'exists:LecturerStaffIDs,StaffIDNumber'];
        }

        if (session('registration_role') === 'student') {
            $rules['student_id_number'] = ['required', 'string', 'exists:StudentIDs,StudentIDNumber'];
            // Only students are restricted to this domain - lecturers keep
            // whatever email their staff ID registration already used.
            $rules['email'][] = 'regex:/@mak\.ug$/i';
        }

        return $rules;
    }

    public function messages(): array
    {
        return [
            'full_name.required' => 'Full name is required.',
            'full_name.min' => 'Full name must be at least 3 characters.',
            'full_name.max' => 'Full name must not exceed 255 characters.',
            'email.required' => 'Email address is required.',
            'email.email' => 'Please enter a valid email address.',
            'email.unique' => 'This email address is already registered.',
            'email.regex' => 'Students must register with a @mak.ug email address.',
            'username.required' => 'Username is required.',
            'username.min' => 'Username must be at least 3 characters.',
            'username.unique' => 'This username is already taken.',
            'username.alpha_dash' => 'Username can only contain letters, numbers, dashes, and underscores.',
            'password.required' => 'Password is required.',
            'password.confirmed' => 'Passwords do not match.',
            'rules_accepted.required' => 'You must accept the platform rules to proceed.',
            'rules_accepted.accepted' => 'You must accept the platform rules to proceed.',
            'staff_id_number.required' => 'Staff ID number is required for lecturer registration.',
            'staff_id_number.exists' => 'This staff ID number was not recognized. Please contact your administrator.',
            'student_id_number.required' => 'Student ID number is required for student registration.',
            'student_id_number.exists' => 'This student ID number was not recognized. Please contact your administrator.',
        ];
    }
}