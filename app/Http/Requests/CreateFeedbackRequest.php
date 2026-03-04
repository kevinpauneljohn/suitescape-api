<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateFeedbackRequest extends FormRequest
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
            'rating' => ['required', 'numeric', 'min:1', 'max:5'],
            'comment' => ['required', 'string'],
            'media' => ['nullable', 'array', 'max:3'],
            'media.*' => ['nullable', 'file', 'mimes:jpg,jpeg,png,gif,mp4,mov,avi', 'max:51200'], // 50MB max per file
        ];
    }
}
