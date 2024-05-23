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
            'rooms' => json_decode($this->rooms, true),
            'addons' => json_decode($this->addons, true),
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

        $bookingDatesRequest = UpdateBookingDatesRequest::createFrom($this);

        return array_merge([
            'listing_id' => ['required', 'string', 'exists:listings,id'],
            'rooms' => ['nullable', 'array'],
            'addons' => ['nullable', 'array'],
            'coupon_code' => ['nullable', 'string'],
            'message' => ['nullable', 'string'],
        ], $bookingDatesRequest->rules());
    }
}
