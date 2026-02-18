<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EnteredKeyValue extends Model
{
    protected $fillable = [
        'key_id',
        'store_id',
        'entry_date',
        'value_text',
        'value_number',
        'value_boolean',
        'value_json',
    ];

    protected $casts = [
        'entry_date' => 'date',
        'value_boolean' => 'boolean',
        'value_json' => 'array',
        'value_number' => 'decimal:4',
    ];

    public function key(): BelongsTo
    {
        return $this->belongsTo(EnteredKey::class, 'key_id');
    }
}
