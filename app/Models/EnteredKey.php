<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EnteredKey extends Model
{
    protected $fillable = [
        'label',
        'data_type',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function storeRules(): HasMany
    {
        return $this->hasMany(KeyStoreRule::class, 'key_id');
    }

    public function values(): HasMany
    {
        return $this->hasMany(EnteredKeyValue::class, 'key_id');
    }
}
