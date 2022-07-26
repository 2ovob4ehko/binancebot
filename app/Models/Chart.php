<?php

namespace App\Models;

use App\Helpers\Intervals;
use Illuminate\Database\Eloquent\Model;

class Chart extends Model
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
