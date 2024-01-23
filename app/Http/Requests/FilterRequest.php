<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class FilterRequest extends FormRequest
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
            'min_price' => ['sometimes', 'numeric'],
            'max_price' => ['sometimes', 'numeric'],
            'check_in' => ['sometimes', 'nullable', 'date', 'before:check_out'],
            'check_out' => ['sometimes', 'nullable', 'date', 'after:check_in'],
            'destination' => ['sometimes', 'nullable', 'string'],
            'facilities' => ['sometimes', 'array'],
            'adults' => ['sometimes', 'numeric'],
            'children' => ['sometimes', 'numeric'],
            'rooms' => ['sometimes', 'numeric'],
            'min_rating' => ['sometimes', 'numeric'],
            'max_rating' => ['sometimes', 'numeric'],
            'amenities' => ['sometimes', 'array'],
        ];
    }
}
