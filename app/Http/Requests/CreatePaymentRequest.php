<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreatePaymentRequest extends FormRequest
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
            'booking_id' => ['required', 'uuid'],
            'amount' => ['required', 'numeric'],
            'description' => ['required', 'string'],
            'payment_method_type' => [
                'required',
                'string',
                'in:card,gcash,paymaya',
            ],

            // Payment Method Details
            'payment_method_details' => [
                'required_if:payment_method_type,card',
                'array',
            ],
            'payment_method_details.card_number' => [
                'required_if:payment_method_type,card',
                'string',
            ],
            'payment_method_details.exp_month' => [
                'required_if:payment_method_type,card',
                'integer',
                'between:1,12',
            ],
            'payment_method_details.exp_year' => [
                'required_if:payment_method_type,card',
                'integer',
                'min:'.date('Y'),
                'max:'.(date('Y'). 50),
            ],
            'payment_method_details.cvc' => [
                'required_if:payment_method_type,card',
                'string',
            ],

            // Billing Details
            'billing_details' => ['required', 'array'],
            'billing_details.name' => ['required', 'string'],
            'billing_details.email' => ['required', 'email'],
            'billing_details.phone' => ['nullable', 'string'],

            // Billing Address
            'billing_address.line1' => ['required', 'string'],
            'billing_address.line2' => ['nullable', 'string'],
            'billing_address.city' => ['required', 'string'],
            'billing_address.state' => ['required', 'string'],
            'billing_address.postal_code' => ['sometimes', 'string'],
            'billing_address.country' => ['sometimes', 'string'],
        ];
    }
}
