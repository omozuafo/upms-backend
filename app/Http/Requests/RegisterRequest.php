<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use App\Models\User;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class RegisterRequest extends FormRequest
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
        return [
            'name' => ['required', 'string', 'min:2', 'max:255'],
            'username' => ['required', 'string', 'regex:/^[a-zA-Z0-9]{3,20}$/', 'unique:users,username'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'phone' => ['required', 'string', 'regex:/^\d{10,15}$/', 'unique:users,phone'],
            'password' => [
                'required',
                'string',
                'min:8',
                'regex:/[A-Z]/',
                'regex:/[0-9]/',
                'regex:/[^a-zA-Z0-9]/',
                'confirmed'
            ],
            'role' => [
                'required',
                'string',
                Rule::in(['admin', 'maintenance', 'maintenance_staff', 'accountant', 'accounting_staff', 'landlord', 'tenant']),
                function ($attribute, $value, $fail) {
                    if ($value === 'admin') {
                        if (User::whereIn('role', ['admin', 'super_admin'])->exists()) {
                            $fail('Registration for the Admin role is closed because an account already exists.');
                        }
                    } elseif (in_array($value, ['maintenance', 'maintenance_staff'])) {
                        if (User::whereIn('role', ['maintenance', 'maintenance_staff'])->exists()) {
                            $fail('Registration for the Maintenance role is closed because an account already exists.');
                        }
                    } elseif (in_array($value, ['accountant', 'accounting_staff'])) {
                        if (User::whereIn('role', ['accountant', 'accounting_staff'])->exists()) {
                            $fail('Registration for the Accountant role is closed because an account already exists.');
                        }
                    }
                }
            ],
        ];
    }

    /**
     * Custom validation messages.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'email.email' => 'Please enter a valid email address.',
            'email.unique' => 'This email is already registered.',
            'phone.regex' => 'Phone number must be a valid numeric format, 10–15 digits.',
            'phone.unique' => 'This phone number is already in use.',
            'username.regex' => 'Username must be alphanumeric only, 3–20 characters, no spaces.',
            'username.unique' => 'This username is already taken.',
            'password.min' => 'Password must be at least 8 characters.',
            'password.regex' => 'Password must contain at least one uppercase letter, one number, and one special character.',
        ];
    }

    /**
     * Handle failed validation to return consistent JSON error response.
     */
    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(response()->json($validator->errors(), 422));
    }
}
