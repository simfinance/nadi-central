<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class NadiAgentCredential extends Model
{
    protected $table = 'nadi_agent_credentials';

    protected $primaryKey = 'id';

    public $incrementing = false;

    protected $keyType = 'string';

    public const CREATED_AT = 'created_at';

    public const UPDATED_AT = null;

    protected $fillable = [
        'agent_id',
        'credential_hash',
        'key_version',
        'status',
        'issued_at',
        'expires_at',
        'revoked_at',
        'last_used_at',
    ];

    protected $casts = [
        'issued_at' => 'datetime',
        'expires_at' => 'datetime',
        'revoked_at' => 'datetime',
        'last_used_at' => 'datetime',
        'created_at' => 'datetime',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model)
        {
            if (!$model->id)
            {
                $model->id = (string) Str::uuid();
            }
        });
    }

    public function agent()
    {
        return $this->belongsTo(
            NadiAgent::class,
            'agent_id'
        );
    }

    public function scopeActive($query)
    {
        return $query
            ->where('status', 'active')
            ->where(function ($query)
            {
                $query->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            })
            ->whereNull('revoked_at');
    }
}