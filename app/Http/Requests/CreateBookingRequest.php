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

        return [
            'room_id' => ['required', 'string'],
            'coupon_id' => ['nullable', 'string'],
            'amount' => ['required', 'numeric'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date'],
            'message' => ['nullable', 'string'],
        ];
    }
}
