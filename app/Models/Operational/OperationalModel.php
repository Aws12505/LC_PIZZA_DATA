<?php

namespace App\Models\Operational;

use Illuminate\Database\Eloquent\Model;

abstract class OperationalModel extends Model
{
    /**
     * The connection name for the model.
     * All operational models use the operational database.
     */
    protected $connection = 'operational';

    /**
     * Indicates if the model should be timestamped.
     */
    public $timestamps = true;

    /**
     * The storage format of the model's date columns.
     */
    protected $casts = [
        'business_date' => 'date',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
}
