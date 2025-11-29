<?php

namespace App\Models\Operational;

class BreadBoostHot extends OperationalModel
{
    protected $table = 'bread_boost_hot';

    protected $fillable = [
        'business_date',
        'franchise_store',
        'classic_order',
        'classic_with_bread',
        'other_pizza_order',
        'other_pizza_with_bread',
    ];

    protected $casts = [
        'business_date' => 'date',
        'classic_order' => 'integer',
        'classic_with_bread' => 'integer',
        'other_pizza_order' => 'integer',
        'other_pizza_with_bread' => 'integer',
    ];

    public function scopeForStore($query, $store)
    {
        return $query->where('franchise_store', $store);
    }

    public function getBreadAttachmentRate(): float
    {
        $total = ($this->classic_order ?? 0) + ($this->other_pizza_order ?? 0);
        $withBread = ($this->classic_with_bread ?? 0) + ($this->other_pizza_with_bread ?? 0);

        return $total > 0 ? ($withBread / $total) * 100 : 0;
    }
}
