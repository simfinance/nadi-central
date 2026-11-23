<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable([
    'agent_id',
    'application_name',
    'application_key',
    'environment',
    'framework',
    'framework_version',
    'php_version',
    'registration_status',
    'activation_status',
    'monitoring_status',
    'registered_at',
    'unregistered_at',
])]
class NadiApplication extends Model
{
    use HasUuids;

    protected $table = 'nadi_applications';

    protected function casts(): array
    {
        return [
            'registered_at' => 'datetime',
            'unregistered_at' => 'datetime',
        ];
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(NadiAgent::class, 'agent_id');
    }

    public function endpoint(): HasOne
    {
        return $this->hasOne(NadiApplicationEndpoint::class, 'application_id');
    }

    public function credentials(): HasMany
    {
        return $this->hasMany(NadiApplicationCredential::class, 'application_id');
    }
}