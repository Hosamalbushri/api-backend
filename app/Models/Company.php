<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Company extends Model
{
    use Concerns\HasUuidPrimaryKey;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'name',
        'code',
        'status',
    ];

    public function syncSequences(): HasMany
    {
        return $this->hasMany(SyncSequence::class, 'company_id');
    }
}
