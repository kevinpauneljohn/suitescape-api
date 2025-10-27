<?php

namespace App\Http\Requests;

use App\Rules\VideoDurationValidation;
use Illuminate\Validation\Rules\File;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Contracts\Validation\Validator;

class CreateListingRequest extends UpdateListingRequest
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
        return array_merge(parent::rules(), [
            'images.*.file' => ['required', File::types(['jpeg', 'png'])],
            'images.*.privacy' => ['required', 'string', 'in:public,private'],
            'videos.*.file' => ['required', File::types(['mp4', 'mov']), new VideoDurationValidation(0, 180)],
            'videos.*.privacy' => ['required', 'string', 'in:public,private'],
            'videos.*.sections' => ['nullable', 'array'],
        ]);
    }

    protected function failedValidation(Validator $validator)
    {
        \Log::error('Validation Failed:', $validator->errors()->toArray());
        throw new HttpResponseException(response()->json([
            'errors' => $validator->errors(),
            'message' => 'Validation failed',
        ], 422));
    }
}
