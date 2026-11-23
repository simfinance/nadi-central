<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class NadiAgentCommand extends Model
{
    protected $table = 'nadi_agent_commands';

    protected $primaryKey = 'id';

    public $incrementing = false;

    protected $keyType = 'string';

    public const CREATED_AT = 'created_at';

    public const UPDATED_AT = null;

    protected $fillable = [
        'agent_id',
        'application_id',
        'command_type',
        'command_payload',
        'command_status',
        'issued_at',
        'delivered_at',
        'completed_at',
        'expires_at',
        'result_payload',
    ];

    protected $casts = [
        'command_payload' => 'array',
        'result_payload' => 'array',
        'issued_at' => 'datetime',
        'delivered_at' => 'datetime',
        'completed_at' => 'datetime',
        'expires_at' => 'datetime',
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

    public function application()
    {
        return $this->belongsTo(
            NadiApplication::class,
            'application_id'
        );
    }
}
