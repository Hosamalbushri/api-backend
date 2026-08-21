<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SyncEntity extends Model
{
    protected $fillable = [
        'company_id',
        'entity_type',
        'entity_uuid',
        'version',
        'payload',
        'deleted_at',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'deleted_at' => 'datetime',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
