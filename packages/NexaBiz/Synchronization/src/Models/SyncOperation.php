<?php

namespace NexaBiz\Synchronization\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use NexaBiz\Identity\Models\Company;

class SyncOperation extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'company_id',
        'operation_id',
        'entity_type',
        'entity_uuid',
        'operation_type',
        'status',
        'result',
        'user_id',
        'device_id',
        'processed_at',
    ];

    protected function casts(): array
    {
        return [
            'result' => 'array',
            'processed_at' => 'datetime',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
