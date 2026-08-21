<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SyncChange extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'sequence',
        'company_id',
        'entity_type',
        'entity_uuid',
        'operation',
        'version',
        'payload',
        'deleted',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'deleted' => 'boolean',
            'sequence' => 'integer',
            'version' => 'integer',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
