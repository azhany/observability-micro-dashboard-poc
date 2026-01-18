<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Alert extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'tenant_id',
        'alert_rule_id',
        'state',
        'started_at',
        'last_checked_at',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'last_checked_at' => 'datetime',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function alertRule(): BelongsTo
    {
        return $this->belongsTo(AlertRule::class);
    }
}
