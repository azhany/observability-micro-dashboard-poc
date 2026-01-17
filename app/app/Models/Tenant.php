<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Tenant extends Model
{
    use HasUuids;

    protected $fillable = [
        'name',
        'settings',
    ];

    protected $casts = [
        'settings' => 'array',
    ];

    public function tokens(): HasMany
    {
        return $this->hasMany(TenantToken::class);
    }
}
