<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'application_id',
    'credential_hash',
    'key_version',
    'status',
    'issued_at',
    'expires_at',
    'revoked_at',
    'last_used_at',
])]
class NadiApplicationCredential extends Model
{
    use HasUuids;

    protected $table = 'nadi_application_credentials';

    public const CREATED_AT = 'created_at';

    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return [
            'issued_at' => 'datetime',
            'expires_at' => 'datetime',
            'revoked_at' => 'datetime',
            'last_used_at' => 'datetime',
        ];
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(
            NadiApplication::class,
            'application_id'
        );
    }
}
