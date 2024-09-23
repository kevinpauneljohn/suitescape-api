<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreatePayoutMethodRequest extends FormRequest
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
        return array_merge(parent::messages(), [
            'phone.phone' => 'The phone number is invalid.',
        ]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'type' => ['required', 'string'],
            'account_name' => ['required', 'string'],
            'account_number' => ['required', 'string'],
            'role' => ['required', 'string', 'in:property_owner,property_manager,hosting_service_provider,other'],
            'bank_name' => ['required', 'string'],
            'bank_type' => ['required', 'string', 'in:personal,joint,business'],
            'swift_code' => ['required', 'string'],
            'bank_code' => ['required', 'string'],
            'email' => ['required', 'email'],
            'phone' => ['required', 'string', 'phone:INTERNATIONAL,PH'],
            'dob' => ['required', 'date'],
            'pob' => ['required', 'string'],
            'citizenship' => ['required', 'string'],
            'billing_country' => ['required', 'string'],
        ];
    }
}
