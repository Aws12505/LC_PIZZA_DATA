<?php

namespace App\Models\Archive;

use Illuminate\Database\Eloquent\Model;

abstract class ArchiveModel extends Model
{
    protected $connection = 'archive';

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
