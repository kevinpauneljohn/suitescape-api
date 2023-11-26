<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ProfileUpdateRequest extends FormRequest
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
            'firstname' => ['required', 'string', 'min:2'],
            'middlename' => ['nullable', 'string', 'min:2'],
            'lastname' => ['required', 'string', 'min:2'],
            'gender' => ['nullable', 'string', 'in:male,female,other'],
            'email' => ['required', 'string', 'email'],
            'address' => ['required', 'string'],
            'zipcode' => ['required', 'string', 'regex:/^\d{4,5}$/'],
            'city' => ['required', 'string'],
            'region' => ['required', 'string'],
            'mobile_number' => ['nullable', 'string', 'regex:/^(?:\+\d{1,3}\s?|0)\d{4,14}$/'], // Matches domestic and internation format
            'date_of_birth' => ['sometimes', 'date', 'before:today'],
        ];
    }
}
