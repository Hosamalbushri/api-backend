<?php

namespace NexaBiz\Synchronization\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PushOperationRequest extends FormRequest
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
            'entity_type' => 'required|string',
            'operation' => 'required|array',
            'operation.operation_id' => 'required|uuid',
            'operation.entity_type' => 'required|string',
            'operation.entity_id' => 'required|uuid',
            'operation.type' => 'required|in:create,update,delete',
            'operation.payload' => 'nullable|array',
            'operation.base_version' => 'nullable|integer',
        ];
    }
}
