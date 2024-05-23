<?php

namespace App\Http\Requests;

use App\Rules\VideoDurationValidation;
use Illuminate\Foundation\Http\FormRequest;

class UploadVideoRequest extends FormRequest
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
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array|string>
     */
    public function rules(): array
    {
        return [
            'video' => ['required', 'file', 'mimetypes:video/mp4,video/quicktime',  new VideoDurationValidation(0, 180)],
            'privacy' => ['sometimes', 'in:public,private'],
        ];
    }
}
