<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

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
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        if ($this->filled('gender')) {
            $this->merge([
                'gender' => Str::lower($this->gender),
            ]);
        }
    }

    /**
     * Get the error messages for the defined validation rules.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'mobile_number.phone' => 'The mobile number is invalid.',
        ];
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $registrationRequest = RegisterUserRequest::createFrom($this);

        return array_merge(Arr::except($registrationRequest->rules(), 'password'), [
            'email' => ['required', 'email', Rule::unique('users', 'email')->ignore($this->user()?->id)],
            'mobile_number' => ['nullable', 'string', 'phone:INTERNATIONAL,PH', Rule::unique('users', 'mobile_number')->ignore($this->user()?->id)],
            'gender' => ['nullable', 'string', 'in:male,female,other'],
            'address' => ['required', 'string'],
            'zipcode' => ['required', 'string', 'regex:/^\d{4,5}$/'],
            'city' => ['required', 'string'],
            'region' => ['required', 'string'],
            'date_of_birth' => ['sometimes', 'date', 'before:18 years ago'],
            'picture' => ['nullable', 'image'],
        ]);
    }
}
