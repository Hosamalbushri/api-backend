<?php

namespace NexaBiz\Synchronization\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PullChangesRequest extends FormRequest
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
            'entity_type' => 'nullable|string',
            'cursor' => 'nullable|integer|min:0',
            'since' => 'nullable|date',
            'limit' => 'nullable|integer|min:1|max:2000',
        ];
    }
}
