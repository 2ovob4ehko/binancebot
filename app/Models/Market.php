<?php

namespace App\Models;

use App\Helpers\Intervals;
use Illuminate\Support\Collection;
use Illuminate\Database\Eloquent\Model;

class Market extends Model
{
    protected $guarded = [];

    protected $casts = [
        'settings' => 'array',
        'data' => 'array',
        'rsi' => 'array',
        'stoch_rsi' => 'array',
        'trade_data' => 'array'
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function simulations(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Simulation::class);
    }

    public function charts(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Chart::class);
    }

    public function chartsData(): Collection
    {
        $page = request()->get('page') ?? 1;
        $limit = request()->get('limit') ?? 1000;
        return $this->charts()->where('type','data')->orderBy('time','desc')->offset(($page-1)*$limit)->limit($limit)->pluck('data');
    }

    public function chartsRsi(): Collection
    {
        $page = request()->get('page') ?? 1;
        $limit = request()->get('limit') ?? 1000;
        return $this->charts()->where('type','rsi')->orderBy('time','desc')->offset(($page-1)*$limit)->limit($limit)->pluck('data');
    }

    public function chartsStochRsi(): Collection
    {
        $page = request()->get('page') ?? 1;
        $limit = request()->get('limit') ?? 1000;
        return $this->charts()->where('type','stoch_rsi')->orderBy('time','desc')->offset(($page-1)*$limit)->limit($limit)->pluck('data');
    }

    public function chartsSmaStochRsi(): Collection
    {
        $page = request()->get('page') ?? 1;
        $limit = request()->get('limit') ?? 1000;
        return $this->charts()->where('type','sma_stoch_rsi')->orderBy('time','desc')->offset(($page-1)*$limit)->limit($limit)->pluck('data');
    }

    public function limit(): int
    {
        $candleDays = Intervals::days();
        return intval($this->settings['period']/$candleDays[$this->settings['candle']]);
    }
}
