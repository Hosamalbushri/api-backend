<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SyncSequence extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    protected $primaryKey = 'company_id';

    public $timestamps = false;

    protected $fillable = [
        'company_id',
        'next_value',
    ];

    protected function casts(): array
    {
        return [
            'next_value' => 'integer',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
