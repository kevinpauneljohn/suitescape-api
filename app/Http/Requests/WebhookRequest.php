<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class WebhookRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Allow all for now, or add your auth logic
    }

    public function rules(): array
    {
        return [
            'data.attributes.url' => ['required', 'url'],
            'data.attributes.events' => ['required', 'array', 'min:1'],
            'data.attributes.events.*' => ['string'],
        ];
    }

    public function messages(): array
    {
        return [
            'data.attributes.url.required' => 'The webhook URL is required.',
            'data.attributes.url.url' => 'The webhook URL must be a valid URL.',
            'data.attributes.events.required' => 'At least one event is required.',
            'data.attributes.events.array' => 'Events must be provided as an array.',
        ];
    }
}
