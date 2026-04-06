<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class BookingHoldRequest extends FormRequest
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
        $this->merge([
            'rooms' => is_string($this->rooms) ? json_decode($this->rooms, true) : ($this->rooms ?? []),
            'addons' => is_string($this->addons) ? json_decode($this->addons, true) : ($this->addons ?? []),
        ]);
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        $bookingDatesRequest = FutureDateRangeRequest::createFrom($this);

        return array_merge([
            'listing_id' => ['required', 'uuid', 'exists:listings,id'],
            'rooms' => ['nullable', 'array'],
            'addons' => ['nullable', 'array'],
            'message' => ['nullable', 'string'],
            'idempotency_key' => ['nullable', 'string', 'max:255'],
        ], $bookingDatesRequest->rules());
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'listing_id.required' => 'A listing is required to create a hold.',
            'listing_id.exists' => 'The selected listing does not exist.',
            'start_date.required' => 'A start date is required.',
            'end_date.required' => 'An end date is required.',
        ];
    }
}
