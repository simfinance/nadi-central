<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class NadiApprovalLog extends Model
{
    protected $table = 'nadi_approval_logs';

    protected $primaryKey = 'id';

    public $incrementing = false;

    protected $keyType = 'string';

    const CREATED_AT = 'created_at';

    const UPDATED_AT = null;

    protected $fillable = [
        'registration_id',
        'application_id',
        'admin_id',
        'action',
        'reason',
    ];

    protected $casts = [
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

    public function registration()
    {
        return $this->belongsTo(
            NadiRegistration::class,
            'registration_id'
        );
    }

    public function application()
    {
        return $this->belongsTo(
            NadiApplication::class,
            'application_id'
        );
    }

    public function admin()
    {
        return $this->belongsTo(
            User::class,
            'admin_id'
        );
    }
}
