<?php

namespace NexaBiz\Identity\Models;

use NexaBiz\Core\Models\Concerns\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Company extends Model
{
    use HasUuidPrimaryKey;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'name',
        'code',
        'status',
    ];

    public function memberships(): HasMany
    {
        return $this->hasMany(CompanyUser::class, 'company_id');
    }
}
