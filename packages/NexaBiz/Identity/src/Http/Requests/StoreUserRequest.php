<?php

namespace NexaBiz\Identity\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreUserRequest extends FormRequest
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
            'name' => 'required|string|min:1|max:200',
            'email' => 'required|string|min:3|max:320',
            'password' => 'required|string|min:8|max:200',
            'phone' => 'nullable|string',
            'status' => 'nullable|string',
            'company_id' => 'nullable|uuid',
            'role_id' => 'nullable|uuid',
            'is_super_admin' => 'nullable|boolean',
        ];
    }
}
