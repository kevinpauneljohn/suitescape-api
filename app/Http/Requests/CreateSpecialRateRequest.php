<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateSpecialRateRequest extends FormRequest
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
        $specialRateDatesRequest = DateRangeRequest::createFrom($this);

        return array_merge([
            'title' => ['required', 'string'],
            'price' => ['required', 'numeric'],
        ], $specialRateDatesRequest->rules());
    }
}
