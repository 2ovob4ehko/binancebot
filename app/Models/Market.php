<?php

namespace App\Models;

use App\Helpers\Intervals;
use Illuminate\Database\Eloquent\Model;

class Market extends Model
{
    protected $guarded = [];

    protected $casts = [
        'settings' => 'array',
        'data' => 'array',
        'rsi' => 'array',
        'stoch_rsi' => 'array'
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function simulations(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Simulation::class);
    }

    public function limit(): int
    {
        $candleDays = Intervals::days();
        return intval($this->settings['period']/$candleDays[$this->settings['candle']]);
    }
}
