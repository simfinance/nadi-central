<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NadiEnrollmentToken extends Model
{
    use HasUuids;

    protected $table = 'nadi_enrollment_tokens';

    protected $keyType = 'string';

    public $incrementing = false;

    public $timestamps = false;

    protected $fillable = [
        'token_hash',
        'token_name',
        'environment_scope',
        'expires_at',
        'used_at',
        'enrollment_request_id',
        'used_by_agent_instance_id',
        'enrollment_payload_hash',
        'response_encrypted',
        'revoked_at',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'used_at' => 'datetime',
            'revoked_at' => 'datetime',
            'created_at' => 'datetime',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function getStatusAttribute(): string
    {
        if ($this->revoked_at)
        {
            return 'revoked';
        }

        if ($this->used_at)
        {
            return 'used';
        }

        if (!$this->expires_at || $this->expires_at->isPast())
        {
            return 'expired';
        }

        return 'active';
    }
}
