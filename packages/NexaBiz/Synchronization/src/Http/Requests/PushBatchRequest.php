<?php

namespace NexaBiz\Synchronization\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PushBatchRequest extends FormRequest
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
            'operations' => 'required|array|min:1',
            'operations.*.operation_id' => 'required|uuid',
            'operations.*.entity_type' => 'required|string',
            'operations.*.entity_id' => 'required|uuid',
            'operations.*.type' => 'required|in:create,update,delete',
            'operations.*.payload' => 'nullable|array',
            'operations.*.base_version' => 'nullable|integer',
        ];
    }
}
