<?php

namespace App\Http\Requests;

use App\Rules\PublicMediaRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateListingRequest extends FormRequest
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
        \Log::info('UpdateListingRequest prepareForValidation called', ['request_data' => $this->all()]);
        $images = [];
        if (!empty($this->images) && is_array($this->images)) {
            foreach ($this->images as $image) {
                if (is_string($image)) {
                    $decoded = json_decode($image, true);
                    $images[] = is_array($decoded) ? $decoded : [];
                } elseif (is_array($image)) {
                    $images[] = $image;
                }
            }
        }

        $videos = [];
        if (!empty($this->videos) && is_array($this->videos)) {
            foreach ($this->videos as $video) {
                if (is_string($video)) {
                    $video = json_decode($video, true) ?? [];
                }

                if (isset($video['sections']) && is_string($video['sections'])) {
                    $video['sections'] = json_decode($video['sections'], true) ?? [];
                }

                $videos[] = $video;
            }
        }

        $this->merge([
            'is_check_in_out_same_day' => filter_var($this->is_check_in_out_same_day, FILTER_VALIDATE_BOOLEAN),
            'is_pet_allowed' => filter_var($this->is_pet_allowed, FILTER_VALIDATE_BOOLEAN),
            'parking_lot' => filter_var($this->parking_lot, FILTER_VALIDATE_BOOLEAN),
            'is_entire_place' => filter_var($this->is_entire_place, FILTER_VALIDATE_BOOLEAN),
            'rooms' => is_string($this->rooms) ? json_decode($this->rooms, true) : $this->rooms,
            'addons' => is_string($this->addons) ? json_decode($this->addons, true) : $this->addons,
            'nearby_places' => is_string($this->nearby_places) ? json_decode($this->nearby_places, true) : $this->nearby_places,
            'images' => $images,
            'videos' => $videos,
        ]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'id' => ['nullable', 'uuid', 'exists:listings,id'],
            'name' => ['required', 'string', 'max:255'],
            'location' => ['required', 'string'],
            'latitude' => ['nullable', 'string', 'max:50'],
            'longitude' => ['nullable', 'string', 'max:50'],
            'description' => ['nullable', 'string'],
            'facility_type' => ['required', 'string', 'in:house,hotel,apartment,condominium,cabin,villa'],
            'check_in_time' => ['required', 'date_format:g:i A'],
            'check_out_time' => ['required', 'date_format:g:i A'],
            'is_check_in_out_same_day' => ['required', 'boolean'],
            'total_hours' => ['required', 'integer'],
            'adult_capacity' => ['required', 'integer'],
            'child_capacity' => ['required', 'integer'],
            'is_pet_allowed' => ['required', 'boolean'],
            'parking_lot' => ['required', 'boolean'],
            'is_entire_place' => ['required', 'boolean'],
            'entire_place_weekday_price' => ['exclude_unless:is_entire_place,true',  'numeric'],
            'entire_place_weekend_price' => ['exclude_unless:is_entire_place,true',  'numeric'],
            'rooms' => ['nullable', 'array'],
            'addons' => ['nullable', 'array'],
            'nearby_places' => ['nullable', 'array'],
            'images' => ['required', 'array', new PublicMediaRule('image')],
            'videos' => ['required', 'array', new PublicMediaRule('video')],
        ];
    }
}
