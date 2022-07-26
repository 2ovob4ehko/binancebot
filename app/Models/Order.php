<?php

namespace App\Models;

use App\Helpers\Intervals;
use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    protected $guarded = [];

    protected $casts = [
        'data' => 'array',
    ];

    public function market()
    {
        return $this->belongsTo(Market::class);
    }
}
