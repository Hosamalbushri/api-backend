<?php

namespace NexaBiz\Audit\Models;

use Illuminate\Database\Eloquent\Model;
use NexaBiz\Core\Models\Concerns\HasUuidPrimaryKey;

class AuditLog extends Model
{
    use HasUuidPrimaryKey;

    public $incrementing = false;

    protected $keyType = 'string';

    public const UPDATED_AT = null;

    protected $fillable = [
        'id',
        'user_id',
        'company_id',
        'device_id',
        'action',
        'entity_type',
        'entity_id',
        'metadata',
        'ip_address',
        'user_agent',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
        ];
    }
}
