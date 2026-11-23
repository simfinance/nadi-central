<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'hostname',
    'ip_address',
    'os_name',
    'os_version',
    'architecture',
    'status',
    'last_seen_at',
])]
class NadiServer extends Model
{
    use HasUuids;

    protected $table = 'nadi_servers';

    protected function casts(): array
    {
        return [
            'last_seen_at' => 'datetime',
        ];
    }
}