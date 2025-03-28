<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateBookingRequest extends FormRequest
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
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        //        $guestInfoRequest = ValidateGuestInfoRequest::createFrom($this);
        //
        //        return array_merge([
        //            'startDate' => ['required', 'date'],
        //            'endDate' => ['required', 'date'],
        //            'nights' => ['required', 'integer'],
        //            'paymentMethod' => ['required', 'string'],
        //        ], $guestInfoRequest->rules());

        $bookingDatesRequest = FutureDateRangeRequest::createFrom($this);

        return array_merge([
            'listing_id' => ['required', 'uuid', 'exists:listings,id'],
            'rooms' => ['nullable', 'array'],
            'addons' => ['nullable', 'array'],
            'coupon_code' => ['nullable', 'string'],
            'message' => ['nullable', 'string'],
        ], $bookingDatesRequest->rules());
    }
}
