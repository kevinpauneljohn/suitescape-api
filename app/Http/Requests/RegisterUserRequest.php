<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RegisterUserRequest extends FormRequest
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
            "firstname" => ["required", "string"],
            "middlename" => ["nullable", "string"],
            "lastname" => ["required", "string"],
            "email" => ["required", "email", "unique:users,email"],
            "mobile_number" => ["nullable", "string"],
            "password" => ["required", "string", "min:8", "confirmed"],
            "date_of_birth" => ["required", "date"],
        ];
    }
}
