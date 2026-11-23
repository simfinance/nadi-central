<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'server_id',
    'agent_instance_id',
    'agent_version',
    'protocol_version',
    'installation_state',
    'status',
    'last_control_at',
    'last_heartbeat_at',
    'installed_at',
    'uninstalled_at',
])]
class NadiAgent extends Model
{
    use HasUuids;

    protected $table = 'nadi_agents';

    protected function casts(): array
    {
        return [
            'last_control_at' => 'datetime',
            'last_heartbeat_at' => 'datetime',
            'installed_at' => 'datetime',
            'uninstalled_at' => 'datetime',
        ];
    }

    public function server(): BelongsTo
    {
        return $this->belongsTo(NadiServer::class, 'server_id');
    }

    public function applications(): HasMany
    {
        return $this->hasMany(NadiApplication::class, 'agent_id');
    }
}