<?php

namespace App\Models;

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

    public function simulations(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Simulation::class);
    }

    public function limit(): int
    {
        $candleDays = [
            '1m' => 1/(24*60),
            '3m' => 1/(24*60)*3,
            '5m' => 1/(24*60)*5,
            '15m' => 1/(24*60)*15,
            '30m' => 1/(24*60)*30,
            '1h' => 1/24,
            '2h' => 1/24*2,
            '4h' => 1/24*4,
            '6h' => 1/24*6,
            '8h' => 1/24*8,
            '12h' => 1/24*12,
            '1d' => 1,
            '3d' => 3,
            '1w' => 7,
            '1M' => 30
        ];
        return intval($this->settings['period']/$candleDays[$this->settings['candle']]);
    }
}
