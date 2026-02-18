<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KeyStoreRule extends Model
{
    protected $fillable = [
        'key_id',
        'store_id',
        'frequency_type',
        'interval',
        'week_days',
        'month_day',
        'week_of_month',
        'week_day',
        'year_month',
        'starts_at',
        'ends_at',
    ];

    protected $casts = [
        'week_days' => 'array',
        'starts_at' => 'date',
        'ends_at' => 'date',
    ];

    public function key(): BelongsTo
    {
        return $this->belongsTo(EnteredKey::class, 'key_id');
    }
}
