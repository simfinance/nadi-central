<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'application_id',
    'base_url',
    'endpoint_prefix',
    'verification_status',
    'last_verified_at',
])]
class NadiApplicationEndpoint extends Model
{
    use HasUuids;

    protected $table = 'nadi_application_endpoints';

    protected function casts(): array
    {
        return [
            'last_verified_at' => 'datetime',
        ];
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(NadiApplication::class, 'application_id');
    }
}