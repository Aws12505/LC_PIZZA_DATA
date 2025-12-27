<?php

namespace App\Models\Aggregation;

use Illuminate\Database\Eloquent\Model;

abstract class AggregationModel extends Model
{
    /**
     * The connection name for the model.
     * All analytics models use the analytics database.
     */
    protected $connection = 'aggregation';

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
