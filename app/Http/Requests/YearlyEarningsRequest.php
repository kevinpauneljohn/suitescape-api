<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class YearlyEarningsRequest extends FormRequest
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
            'host_id' => ['nullable', 'uuid', 'exists:users,id'],
            'listing_id' => ['nullable', 'uuid', 'exists:listings,id'],
        ];
    }
}
