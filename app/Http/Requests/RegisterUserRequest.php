<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class RegisterUserRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the error messages for the defined validation rules.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'firstname.regex' => 'First name must only contain letters and spaces.',
            'middlename.regex' => 'Middle name must only contain letters and spaces.',
            'lastname.regex' => 'Last name must only contain letters and spaces.',
            'date_of_birth.before' => 'You must be 18 years old or above to register.',
        ];
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array|string>
     */
    public function rules(): array
    {
        return [
            'firstname' => ['required', 'string', 'regex:/^[a-zA-Z\sñÑ]*$/'],
            'middlename' => ['nullable', 'string', 'regex:/^[a-zA-Z\sñÑ]*$/'],
            'lastname' => ['required', 'string', 'regex:/^[a-zA-Z\sñÑ]*$/'],
            'email' => ['required', 'email', 'unique:users,email'],
            'mobile_number' => ['nullable', 'string', 'unique:users,mobile_number', 'regex:/^(?:\+\d{1,3}\s?|0)\d{4,14}$/'],
            'password' => ['required', 'bail', 'confirmed',
                Password::min(8)->letters()->mixedCase()->numbers()->symbols(),
            ],
            'date_of_birth' => ['required', 'date', 'before:18 years ago'],
        ];
    }
}
