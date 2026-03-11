<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeDebrief extends Model
{

    protected $fillable = [
        'store_id',
        'user_id',
        'employee_name',
        'note',
        'date'
    ];

    protected $casts = [
        'date' => 'date'
    ];

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

}