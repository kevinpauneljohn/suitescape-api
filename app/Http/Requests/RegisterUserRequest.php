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
            'firstname' => ['required', 'string'],
            'middlename' => ['nullable', 'string'],
            'lastname' => ['required', 'string'],
            'email' => ['required', 'email', 'unique:users,email'],
            'mobile_number' => ['nullable', 'string'],
            'password' => ['required', 'confirmed',
                Password::min(8)->letters()->mixedCase()->numbers()->symbols(),
            ],
            'date_of_birth' => ['required', 'date', 'before:18 years ago'],
        ];
    }
}
