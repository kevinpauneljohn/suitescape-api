<?php

namespace App\Http\Requests;

use App\Traits\ArrayPrefixTrait;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class PayoutMethodRequest extends FormRequest
{
    use ArrayPrefixTrait;

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
            'phone_number.phone' => 'The phone number is invalid.',
        ]);
    }

    /**
     * Get custom attributes for validator errors.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        // Get the rules
        $rules = $this->rules();

        // Remove the prefixes for the keys
        $removedPrefixes = $this->removePrefixFromKeys($rules, 'account');

        // Combine both of the keys
        return array_combine(array_keys($rules), array_keys($removedPrefixes));
    }

    /**
     * Get the "after" validation callables for the request.
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                // Get the error keys and messages
                $errorKeys = $validator->errors()->keys();
                $errorMessages = $validator->errors()->toArray();

                // Remove the prefixed keys from the errors
                foreach ($errorKeys as $key) {
                    $validator->errors()->forget($key);
                }

                // Merge the keys for the errors with the prefixes removed
                $validator->errors()->merge($this->removePrefixFromKeys($errorMessages, 'account'));
            }
        ];
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $baseRules = [
            'type' => ['required', 'string', 'in:gcash,paypal,bank'],
            'is_default' => ['nullable', 'boolean'],
        ];

        return array_merge($baseRules, $this->getTypeSpecificRules());
    }

    private function getTypeSpecificRules(): array
    {
        $rules = match ($this->type) {
            'gcash' => [
                'phone_number' => ['required', 'string', 'phone:INTERNATIONAL,PH'],
                'account_name' => ['required', 'string', 'max:255'],
            ],
            'paypal' => [
                'email' => ['required', 'email', 'max:255'],
                'account_name' => ['required', 'string', 'max:255'],
            ],
            'bank' => [
                'account_name' => ['required', 'string'],
                'account_number' => ['required', 'string'],
                'role' => ['required', 'string', 'in:property_owner,property_manager,hosting_service_provider,other'],
                'bank_name' => ['required', 'string'],
                'bank_type' => ['required', 'string', 'in:personal,joint,business'],
                'swift_code' => ['required', 'string'],
                'bank_code' => ['required', 'string'],
                'email' => ['required', 'email'],
                'phone_number' => ['required', 'string', 'phone:INTERNATIONAL,PH'],
                'dob' => ['required', 'date'],
                'pob' => ['required', 'string'],
                'citizenship' => ['required', 'string'],
                'billing_country' => ['required', 'string'],
            ],
            default => [],
        };

        return $this->addPrefixToKeys($rules, 'account');
    }
}
