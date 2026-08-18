<?php

namespace NexaBiz\Identity\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'email' => 'required|string|min:3|max:320',
            'password' => 'required|string|min:1',
            'company_id' => 'nullable|uuid',
            'device_id' => 'nullable|uuid',
            'device_name' => 'nullable|string',
            'platform' => 'nullable|string',
            'app_version' => 'nullable|string',
        ];
    }
}
