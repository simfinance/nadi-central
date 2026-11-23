<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'application_id',
    'agent_id',
    'registration_status',
    'requested_at',
    'reviewed_at',
    'reviewed_by',
    'rejection_reason',
])]
class NadiRegistration extends Model
{
    use HasUuids;

    protected $table = 'nadi_registrations';

    protected function casts(): array
    {
        return [
            'requested_at' => 'datetime',
            'reviewed_at' => 'datetime',
        ];
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(NadiApplication::class, 'application_id');
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(NadiAgent::class, 'agent_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
