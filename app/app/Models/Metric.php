<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Metric extends Model
{
    protected $table = 'metrics_raw';

    public $timestamps = false;

    protected $fillable = [
        'tenant_id',
        'agent_id',
        'metric_name',
        'value',
        'timestamp',
        'dedupe_id',
    ];

    protected $casts = [
        'timestamp' => 'datetime:Y-m-d H:i:s.u',
        'value' => 'double',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
