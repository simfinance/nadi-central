<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class NadiActivationGrant extends Model
{
    protected $table = 'nadi_activation_grants';

    protected $primaryKey = 'id';

    public $incrementing = false;

    protected $keyType = 'string';

    const CREATED_AT = 'created_at';

    const UPDATED_AT = null;

    protected $fillable = [
        'id',
        'application_id',
        'agent_id',
        'registration_id',
        'grant_version',
        'key_id',
        'grant_payload',
        'signature',
        'grant_status',
        'acknowledgement_status',
        'acknowledgement_request_id',
        'acknowledged_at',
        'acknowledgement_error_code',
        'acknowledgement_message',
        'issued_at',
        'expires_at',
        'revoked_at',
    ];

    protected $casts = [
        'grant_version' => 'integer',
        'grant_payload' => 'array',
        'issued_at' => 'datetime',
        'expires_at' => 'datetime',
        'revoked_at' => 'datetime',
        'acknowledged_at' => 'datetime',
        'created_at' => 'datetime',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model)
        {
            if (empty($model->id))
            {
                $model->id = (string) Str::uuid();
            }
        });
    }

    public function application()
    {
        return $this->belongsTo(
            NadiApplication::class,
            'application_id'
        );
    }

    public function agent()
    {
        return $this->belongsTo(
            NadiAgent::class,
            'agent_id'
        );
    }

    public function registration()
    {
        return $this->belongsTo(
            NadiRegistration::class,
            'registration_id'
        );
    }
}
