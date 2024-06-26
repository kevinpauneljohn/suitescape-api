<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateReviewRequest extends FormRequest
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
        $rules['listing_id'] = ['required', 'uuid', 'exists:listings,id'];
        $rules['feedback'] = ['nullable', 'string', 'max:500'];

        $serviceRatings = [
            'overall_rating',
            'cleanliness',
            'price_affordability',
            'facility_service',
            'comfortability',
            'staff',
            'location',
            'privacy_and_security',
            'accessibility',
        ];

        foreach ($serviceRatings as $serviceRating) {
            $rules[$serviceRating] = ['required', 'numeric', 'min:0', 'max:5'];
        }

        return $rules;
    }
}
