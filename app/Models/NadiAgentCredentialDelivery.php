<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class NadiAgentCredentialDelivery extends Model
{
    protected $table = 'nadi_agent_credential_deliveries';

    protected $primaryKey = 'id';

    public $incrementing = false;

    public $timestamps = false;

    protected $keyType = 'string';

    protected $fillable = [
        'agent_id',
        'old_credential_id',
        'new_credential_id',
        'encrypted_token',
        'delivery_status',
        'expires_at',
        'retrieved_at',
        'acknowledged_at',
        'created_at',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'retrieved_at' => 'datetime',
        'acknowledged_at' => 'datetime',
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

    public function oldCredential()
    {
        return $this->belongsTo(
            NadiAgentCredential::class,
            'old_credential_id'
        );
    }

    public function newCredential()
    {
        return $this->belongsTo(
            NadiAgentCredential::class,
            'new_credential_id'
        );
    }
}
